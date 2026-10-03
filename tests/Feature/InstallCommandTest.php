<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Itxshakil\FormShield\Console\InstallCommand;
use Itxshakil\FormShield\Enums\StorageOption;
use Itxshakil\FormShield\FormShieldServiceProvider;
use Itxshakil\FormShield\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class InstallCommandTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = sys_get_temp_dir().'/form-shield-'.bin2hex(random_bytes(4));
        $this->app->useDatabasePath($this->database);

        Schema::create('inquiries', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->database);
        @unlink(config_path('form-shield.php'));

        parent::tearDown();
    }

    /** @return list<string> */
    private function migrations(): array
    {
        return array_map(basename(...), glob($this->database.'/migrations/*.php') ?: []);
    }

    #[Test]
    public function it_publishes_the_config_without_a_trailing_double_period(): void
    {
        $output = $this->captureInstallOutput(['--no-interaction' => true]);

        self::assertStringContainsString('Published config [config/form-shield.php].', $output);
        self::assertStringNotContainsString('..', $output);
        self::assertFileExists(config_path('form-shield.php'));
    }

    #[Test]
    public function a_rerun_keeps_an_edited_config_and_says_so(): void
    {
        $this->artisan('form-shield:install', ['--no-interaction' => true])->assertSuccessful();
        file_put_contents(config_path('form-shield.php'), "<?php return ['edited' => true];\n");

        $output = $this->captureInstallOutput(['--no-interaction' => true]);
        self::assertStringContainsString('already exists, skipped. Use --force', $output);
        self::assertStringNotContainsString('Published config', $output);

        self::assertStringContainsString('edited', (string) file_get_contents(config_path('form-shield.php')));

        $this->artisan('form-shield:install', ['--no-interaction' => true, '--force' => true])
            ->expectsOutputToContain('Overwrote config')
            ->assertSuccessful();

        self::assertStringNotContainsString("'edited'", (string) file_get_contents(config_path('form-shield.php')));
    }

    #[Test]
    public function non_interactive_without_flags_says_storage_was_skipped(): void
    {
        $this->artisan('form-shield:install', ['--no-interaction' => true])
            ->expectsOutputToContain('Re-run with --table=<your_table> or --log')
            ->assertSuccessful();

        self::assertSame([], $this->migrations());
    }

    #[Test]
    public function choosing_an_existing_table_writes_the_columns_migration_and_offers_to_migrate(): void
    {
        $this->artisan('form-shield:install')
            ->expectsChoice(InstallCommand::STORAGE_QUESTION, StorageOption::Columns->label(), StorageOption::labels())
            ->expectsQuestion('Which table stores the submissions?', 'inquiries')
            ->expectsConfirmation('Run migrations now?', 'yes')
            ->expectsOutputToContain('Inquiry::createWithVerdict($data, $verdict);')
            ->assertSuccessful();

        self::assertCount(1, $this->migrations());
        self::assertStringEndsWith('_add_spam_columns_to_inquiries_table.php', $this->migrations()[0]);
        self::assertTrue(Schema::hasColumn('inquiries', 'is_spam'));
    }

    #[Test]
    public function the_columns_path_prints_one_set_of_next_steps(): void
    {
        $this->artisan('form-shield:install', ['--table' => 'inquiries', '--no-interaction' => true])
            ->expectsOutputToContain('Next:')
            ->assertSuccessful();

        $output = $this->captureInstallOutput(['--table' => 'inquiries', '--no-interaction' => true]);

        self::assertSame(1, substr_count($output, 'Next:'));
    }

    #[Test]
    public function choosing_the_built_in_log_writes_its_migration(): void
    {
        $this->artisan('form-shield:install')
            ->expectsChoice(InstallCommand::STORAGE_QUESTION, StorageOption::Log->label(), StorageOption::labels())
            ->expectsConfirmation('Run migrations now?', 'no')
            ->expectsOutputToContain('FormShield::record($request, $verdict);')
            ->assertSuccessful();

        self::assertCount(1, $this->migrations());
        self::assertStringEndsWith('_create_form_shield_submissions_table.php', $this->migrations()[0]);
        self::assertFalse(Schema::hasTable('form_shield_submissions'));
    }

    #[Test]
    public function skipping_writes_no_migration_and_asks_nothing_more(): void
    {
        $this->artisan('form-shield:install')
            ->expectsChoice(InstallCommand::STORAGE_QUESTION, StorageOption::Skip->label(), StorageOption::labels())
            ->assertSuccessful();

        self::assertSame([], $this->migrations());
    }

    #[Test]
    public function options_skip_the_questions_and_can_migrate(): void
    {
        $this->artisan('form-shield:install', ['--table' => 'inquiries', '--log' => true, '--migrate' => true])
            ->assertSuccessful();

        self::assertCount(2, $this->migrations());
        self::assertTrue(Schema::hasColumn('inquiries', 'is_spam'));
        self::assertTrue(Schema::hasTable('form_shield_submissions'));
    }

    #[Test]
    public function an_invalid_table_name_fails_before_anything_else_and_exits_non_zero(): void
    {
        self::assertSame(1, Artisan::call('form-shield:install', ['--table' => 'bad-name', '--no-interaction' => true]));

        $output = Artisan::output();
        self::assertStringContainsString('not a valid table name', $output);
        self::assertStringNotContainsString('does not exist yet', $output);
        self::assertStringNotContainsString('Form Shield is installed', $output);

        self::assertSame([], $this->migrations());
    }

    #[Test]
    public function a_table_that_already_has_the_columns_fails(): void
    {
        Schema::table('inquiries', static fn (Blueprint $table) => $table->spamColumns());

        $this->artisan('form-shield:install', ['--table' => 'inquiries', '--no-interaction' => true])
            ->expectsOutputToContain('already has spam columns')
            ->assertFailed();
    }

    #[Test]
    public function a_missing_table_gets_its_migration_written_but_never_run(): void
    {
        $this->artisan('form-shield:install', ['--table' => 'leads', '--log' => true, '--migrate' => true])
            ->expectsOutputToContain('does not exist yet, so the migration was not run')
            ->assertSuccessful();

        // Neither migration ran: `migrate` would have hit the missing table.
        self::assertCount(2, $this->migrations());
        self::assertFalse(Schema::hasTable('form_shield_submissions'));
    }

    #[Test]
    public function the_log_migration_is_only_published_once(): void
    {
        $this->artisan('form-shield:install', ['--log' => true, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('form-shield:install', ['--log' => true, '--no-interaction' => true])
            ->expectsOutputToContain('already in database/migrations')
            ->assertSuccessful();

        self::assertCount(1, $this->migrations());
    }

    #[Test]
    public function printed_hints_never_contain_a_spread(): void
    {
        $output = $this->captureInstallOutput(['--table' => 'inquiries', '--log' => true, '--no-interaction' => true]);

        self::assertStringNotContainsString('...', $output);
        self::assertStringContainsString("'--model' => [\\Itxshakil\\FormShield\\Models\\ShieldSubmission::class],", $output);
    }

    #[Test]
    public function the_log_migration_can_also_be_published_with_vendor_publish(): void
    {
        $targets = ServiceProvider::pathsToPublish(FormShieldServiceProvider::class, 'form-shield-migrations');
        $directory = dirname((string) array_values($targets)[0]);
        $before = glob($directory.'/*_create_form_shield_submissions_table.php') ?: [];

        $this->artisan('vendor:publish', ['--tag' => 'form-shield-migrations'])->assertSuccessful();

        $published = array_values(array_diff(glob($directory.'/*_create_form_shield_submissions_table.php') ?: [], $before));

        try {
            self::assertCount(1, $published);
            self::assertStringNotContainsString('2026_01_01_000000', $published[0]);
        } finally {
            array_map(unlink(...), $published);
        }
    }

    /** @param array<string, mixed> $parameters */
    private function captureInstallOutput(array $parameters): string
    {
        Artisan::call('form-shield:install', $parameters);

        return Artisan::output();
    }
}
