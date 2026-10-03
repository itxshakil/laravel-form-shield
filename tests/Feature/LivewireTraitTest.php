<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Livewire\WithFormShield;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exercises the trait without Livewire installed. Livewire calls
 * mountWithFormShield() itself, and syncs formShield from wire:model.
 */
final class LivewireTraitTest extends TestCase
{
    private function makeComponent(): object
    {
        return new class
        {
            use WithFormShield;

            public string $name = 'Jane Doe';

            public string $email = 'jane@example.com';

            public string $message = 'I would like to ask about your VPS plans please.';

            public function all(): array
            {
                return ['name' => $this->name, 'email' => $this->email, 'message' => $this->message, 'formShield' => $this->formShield];
            }

            public function save(string $profile = 'default', ?array $data = null): Verdict
            {
                return $this->inspectFormShield($profile, $data);
            }

            public function reset(): void
            {
                $this->resetFormShield();
            }

            /** What the browser does: the shield script copies the token on first input. */
            public function arm(): void
            {
                $this->formShield['_fs_js'] = $this->formShield['_fs_started'];
            }
        };
    }

    #[Test]
    public function mount_mints_fields_into_component_state(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();

        self::assertSame(['fax_number', '_fs_started', '_fs_js'], array_keys($component->formShield));
        self::assertNotSame('', $component->formShield['_fs_started']);
    }

    #[Test]
    public function an_armed_human_submission_is_clean(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $component->arm();

        Carbon::setTestNow(Carbon::now()->addSeconds(20));

        $this->app->instance('request', Request::create('/livewire/update', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']));

        self::assertTrue($component->save()->passes());
    }

    #[Test]
    public function a_bot_that_fills_the_honeypot_is_flagged(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $component->formShield['fax_number'] = 'bot';

        Carbon::setTestNow(Carbon::now()->addSeconds(20));

        self::assertSame('honeypot', $component->save()->reason);
    }

    #[Test]
    public function an_instant_submit_is_too_fast(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $component->arm();

        self::assertSame('too_fast', $component->save()->reason);
    }

    #[Test]
    public function explicit_data_is_scored_instead_of_public_properties(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $component->arm();

        Carbon::setTestNow(Carbon::now()->addSeconds(20));

        $verdict = $component->save(data: ['name' => 'www.spam.test', 'message' => 'x']);

        self::assertArrayHasKey('url_in_name', $verdict->signals);
    }

    #[Test]
    public function the_shield_cannot_be_overridden_by_form_data(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $component->formShield['fax_number'] = 'bot';

        Carbon::setTestNow(Carbon::now()->addSeconds(20));

        self::assertSame('honeypot', $component->save(data: ['fax_number' => ''])->reason);
    }

    #[Test]
    public function reset_mints_a_new_token(): void
    {
        $component = $this->makeComponent();
        $component->mountWithFormShield();
        $first = $component->formShield['_fs_started'];

        $component->reset();

        self::assertNotSame($first, $component->formShield['_fs_started']);
    }
}
