<?php

declare(strict_types=1);

namespace Itxshakil\FormShield;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Itxshakil\FormShield\Console\ColumnsCommand;
use Itxshakil\FormShield\Console\InstallCommand;
use Itxshakil\FormShield\Console\ReportCommand;
use Itxshakil\FormShield\Contracts\Inspector as InspectorContract;
use Itxshakil\FormShield\Contracts\MxResolver;
use Itxshakil\FormShield\Contracts\SenderExemption;
use Itxshakil\FormShield\Database\SpamColumns;
use Itxshakil\FormShield\Dns\NativeMxResolver;
use Itxshakil\FormShield\Enums\BuiltInSignal;
use Itxshakil\FormShield\Exemptions\KnownSenderExemption;
use Itxshakil\FormShield\Http\Controllers\FieldsController;
use Itxshakil\FormShield\Http\Middleware\InspectSubmission;
use Itxshakil\FormShield\Support\Value;
use Itxshakil\FormShield\View\Components\FormShield as FormShieldComponent;

final class FormShieldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/form-shield.php', 'form-shield');

        // Token needs StringEncrypter, which the container resolves to the app's encrypter.
        $this->app->singleton(Token::class);
        $this->app->bindIf(MxResolver::class, NativeMxResolver::class);
        $this->app->bindIf(SenderExemption::class, KnownSenderExemption::class);
        $this->app->bindIf(InspectorContract::class, Inspector::class);

        $this->app->singleton(SignalRegistry::class, function (Application $app): SignalRegistry {
            $registry = new SignalRegistry($app);

            foreach (BuiltInSignal::cases() as $signal) {
                $registry->register($signal->value, $signal->signalClass());
            }

            return $registry;
        });

        $this->app->singleton(FormShield::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'form-shield');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'form-shield');

        Blade::component('form-shield', FormShieldComponent::class);
        Blade::directive('formShieldScripts', static fn (): string => '<?php echo \\'.self::class.'::scriptTag(); ?>');

        SpamColumns::register();

        Request::macro('spamVerdict', function (): ?Verdict {
            /** @var Request $this */
            return app(FormShield::class)->verdict($this);
        });

        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('form-shield', InspectSubmission::class);
        });

        if ($this->app->make('config')->get('form-shield.route.enabled')) {
            $this->registerRoute();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ReportCommand::class, InstallCommand::class, ColumnsCommand::class]);

            $this->publishes([__DIR__.'/../config/form-shield.php' => config_path('form-shield.php')], 'form-shield-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/form-shield')], 'form-shield-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/form-shield')], 'form-shield-lang');
            $this->publishes([__DIR__.'/../resources/js' => public_path('vendor/form-shield')], 'form-shield-assets');
            // The date is a placeholder: vendor:publish replaces it with the current time.
            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_form_shield_submissions_table.php.stub' => database_path('migrations/2026_01_01_000000_create_form_shield_submissions_table.php'),
            ], 'form-shield-migrations');
        }
    }

    /** The inline shield script, carrying Vite's CSP nonce when one is set. */
    public static function scriptTag(): string
    {
        static $script = null;

        $script ??= (string) file_get_contents(__DIR__.'/../resources/js/form-shield.js');

        return app(ViewFactory::class)->make('form-shield::script', [
            'script' => $script,
            'nonce' => Vite::cspNonce(),
        ])->render();
    }

    private function registerRoute(): void
    {
        $config = $this->app->make('config');

        // name() before get(), so the route enters the name lookup when it's added.
        Route::name(Value::string($config->get('form-shield.route.name'), 'form-shield.fields'))
            ->middleware(Value::strings($config->get('form-shield.route.middleware')))
            ->get(Value::string($config->get('form-shield.route.uri'), 'form-shield/fields'), FieldsController::class);
    }
}
