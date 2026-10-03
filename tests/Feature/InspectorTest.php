<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Itxshakil\FormShield\Contracts\DeferredSignal;
use Itxshakil\FormShield\Enums\Reason;
use Itxshakil\FormShield\Events\SubmissionInspected;
use Itxshakil\FormShield\Events\SubmissionQuarantined;
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Tests\Concerns\CreatesTables;
use Itxshakil\FormShield\Tests\Fixtures\User;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

final class InspectorTest extends TestCase
{
    use CreatesTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        config()->set('form-shield.exemption.enabled', true);
    }

    /** @param array<string, mixed> $data */
    private function inspect(array $data, string $profile = 'default', ?string $userAgent = 'Mozilla/5.0', ?string $ip = '198.51.100.7'): Verdict
    {
        return FormShield::inspect(Submission::fromArray($data, $ip, $userAgent), $profile);
    }

    #[Test]
    public function a_human_submission_is_clean(): void
    {
        $verdict = $this->inspect($this->humanPayload());

        self::assertTrue($verdict->passes());
        self::assertSame(0, $verdict->score);
        self::assertSame([], $verdict->signals);
    }

    #[Test]
    public function a_filled_honeypot_is_a_hard_flag(): void
    {
        $verdict = $this->inspect($this->humanPayload(['fax_number' => '555-0100']));

        self::assertTrue($verdict->isSpam);
        self::assertSame('honeypot', $verdict->reason);
    }

    #[Test]
    public function an_array_in_the_honeypot_still_counts_as_filled(): void
    {
        self::assertSame('honeypot', $this->inspect($this->humanPayload(['fax_number' => ['x']]))->reason);
    }

    #[Test]
    public function a_missing_or_forged_token_is_a_hard_flag(): void
    {
        $payload = $this->humanPayload();
        unset($payload['_fs_started']);

        self::assertSame('no_token', $this->inspect($payload)->reason);
        self::assertSame('no_token', $this->inspect($this->humanPayload(['_fs_started' => (string) time()]))->reason);
    }

    #[Test]
    public function a_submission_faster_than_min_seconds_is_a_hard_flag(): void
    {
        self::assertSame('too_fast', $this->inspect($this->humanPayload(secondsAgo: 1))->reason);
    }

    #[Test]
    public function a_form_open_past_max_age_is_expired_not_spam(): void
    {
        $verdict = $this->inspect($this->humanPayload(secondsAgo: 43201));

        self::assertTrue($verdict->isExpired);
        self::assertFalse($verdict->isSpam);
    }

    #[Test]
    public function soft_signals_accumulate_until_the_threshold(): void
    {
        // no_js (2) alone: under the threshold of 4.
        $verdict = $this->inspect($this->humanPayload(['_fs_js' => '']));
        self::assertFalse($verdict->isSpam);
        self::assertSame(['no_js' => 2], $verdict->signals);

        // no_js (2) + url_in_name (3) = 5.
        $verdict = $this->inspect($this->humanPayload(['_fs_js' => '', 'name' => 'Visit www.cheap-pills.test', 'message' => 'A second, different message.']));
        self::assertTrue($verdict->isSpam);
        self::assertSame(Reason::Score->value, $verdict->reason);
        self::assertSame(Reason::Score, $verdict->knownReason());
        self::assertSame(5, $verdict->score);
        self::assertSame(['no_js' => 2, 'url_in_name' => 3], $verdict->signals);
    }

    #[Test]
    public function no_single_default_weight_reaches_the_threshold(): void
    {
        $profile = FormShield::profile();

        foreach ($profile->weights() as $signal => $weight) {
            self::assertLessThan($profile->threshold(), $weight, "Signal [{$signal}] can quarantine on its own.");
        }
    }

    #[Test]
    public function the_same_message_twice_fires_duplicate(): void
    {
        self::assertArrayNotHasKey('duplicate', $this->inspect($this->humanPayload())->signals);
        self::assertSame(['duplicate' => 3], $this->inspect($this->humanPayload())->signals);
    }

    #[Test]
    public function disposable_domains_and_their_subdomains_fire(): void
    {
        self::assertArrayHasKey('disposable_email', $this->inspect($this->humanPayload(['email' => 'x@mailinator.com']))->signals);
        self::assertArrayHasKey('disposable_email', $this->inspect($this->humanPayload(['email' => 'x@eu.mailinator.com', 'message' => 'Another message entirely here.']))->signals);
    }

    #[Test]
    public function disposable_domains_can_come_from_a_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fs');
        file_put_contents($file, "# comment\nburner.test\n\n");
        config()->set('form-shield.disposable_domains_file', $file);

        self::assertArrayHasKey('disposable_email', $this->inspect($this->humanPayload(['email' => 'a@burner.test']))->signals);

        unlink($file);
    }

    #[Test]
    public function the_mx_lookup_is_skipped_once_the_verdict_is_decided(): void
    {
        $this->mx->deadDomains = ['nowhere.test'];

        // Under threshold: lookup runs and fires.
        $verdict = $this->inspect($this->humanPayload(['email' => 'a@nowhere.test']));
        self::assertSame(['no_mx' => 2], $verdict->signals);
        self::assertSame(['nowhere.test'], $this->mx->lookups);

        // Already over threshold (no_js 2 + url_in_name 3): no lookup.
        $this->mx->lookups = [];
        $this->inspect($this->humanPayload(['email' => 'b@other.test', '_fs_js' => '', 'name' => 'http://spam.test']));
        self::assertSame([], $this->mx->lookups);
    }

    #[Test]
    public function mx_results_are_cached_per_domain(): void
    {
        $this->inspect($this->humanPayload(['email' => 'a@cached.test']));
        $this->inspect($this->humanPayload(['email' => 'b@cached.test', 'message' => 'A different message so no duplicate.']));

        self::assertSame(['cached.test'], $this->mx->lookups);
    }

    #[Test]
    public function the_dns_check_can_be_disabled(): void
    {
        config()->set('form-shield.email_dns_check', false);
        $this->mx->deadDomains = ['nowhere.test'];

        self::assertSame([], $this->inspect($this->humanPayload(['email' => 'a@nowhere.test']))->signals);
        self::assertSame([], $this->mx->lookups);
    }

    #[Test]
    public function mostly_foreign_script_messages_fire(): void
    {
        $verdict = $this->inspect($this->humanPayload(['message' => 'Привет, купите наши услуги продвижения сайта прямо сейчас']));
        self::assertArrayHasKey('foreign_script', $verdict->signals);

        config()->set('form-shield.allowed_scripts', ['Latin', 'Cyrillic']);
        $verdict = $this->inspect($this->humanPayload(['message' => 'Привет, это другое сообщение о хостинге сайта']));
        self::assertArrayNotHasKey('foreign_script', $verdict->signals);

        config()->set('form-shield.allowed_scripts', null);
        $verdict = $this->inspect($this->humanPayload(['message' => '这是一个关于网站托管的完全不同的消息内容']));
        self::assertArrayNotHasKey('foreign_script', $verdict->signals);
    }

    #[Test]
    public function a_missing_user_agent_fires(): void
    {
        self::assertSame(['no_user_agent' => 2], $this->inspect($this->humanPayload(), userAgent: null)->signals);
    }

    #[Test]
    public function gibberish_in_name_or_message_fires(): void
    {
        self::assertArrayHasKey('gibberish', $this->inspect($this->humanPayload(['name' => 'xKqLmPzRw']))->signals);
    }

    #[Test]
    public function unexpected_fields_fire_only_when_an_allowlist_is_set(): void
    {
        $payload = $this->humanPayload(['website' => 'http://x.test', '_token' => 'abc']);

        self::assertArrayNotHasKey('unexpected_fields', $this->inspect($payload)->signals);

        config()->set('form-shield.allowed_fields', ['name', 'email', 'message']);

        self::assertArrayHasKey('unexpected_fields', $this->inspect($payload)->signals);
        self::assertArrayNotHasKey('unexpected_fields', $this->inspect($this->humanPayload(['_token' => 'abc', 'message' => 'Unique text to avoid dup']))->signals);
    }

    #[Test]
    public function a_known_sender_skips_soft_scoring(): void
    {
        User::query()->create(['email' => 'customer@example.com']);

        $verdict = $this->inspect($this->humanPayload([
            'email' => 'Customer@Example.com',
            '_fs_js' => '',
            'name' => 'www.my-shop.test',
        ]));

        self::assertTrue($verdict->passes());
        self::assertSame(['known_sender' => 0], $verdict->signals);
    }

    #[Test]
    public function a_known_sender_does_not_bypass_hard_signals(): void
    {
        User::query()->create(['email' => 'customer@example.com']);

        $verdict = $this->inspect($this->humanPayload(['email' => 'customer@example.com', 'fax_number' => 'gotcha']));

        self::assertSame('honeypot', $verdict->reason);
    }

    #[Test]
    public function the_exemption_can_be_disabled(): void
    {
        User::query()->create(['email' => 'customer@example.com']);
        config()->set('form-shield.exemption.enabled', false);

        self::assertSame(['no_js' => 2], $this->inspect($this->humanPayload(['email' => 'customer@example.com', '_fs_js' => '']))->signals);
    }

    #[Test]
    public function profiles_change_threshold_inputs_and_signal_set(): void
    {
        config()->set('form-shield.profiles.booking', [
            'threshold' => 2,
            'inputs' => ['message' => ['remarks']],
            'signals' => ['no_js', 'url_in_name'],
        ]);

        $verdict = $this->inspect($this->humanPayload(['_fs_js' => '']), 'booking');
        self::assertTrue($verdict->isSpam);
        self::assertSame('booking', $verdict->profile);

        // no_user_agent isn't in this profile's signal list.
        $verdict = $this->inspect($this->humanPayload(), 'booking', userAgent: null);
        self::assertTrue($verdict->passes());
    }

    #[Test]
    public function custom_soft_signals_carry_their_weight(): void
    {
        FormShield::extend('mentions_crypto', static fn (Submission $s, Profile $p): bool => str_contains(strtolower($p->message($s)), 'crypto'), weight: 3);

        $verdict = $this->inspect($this->humanPayload(['message' => 'Invest in crypto today']));

        self::assertSame(['mentions_crypto' => 3], $verdict->signals);
    }

    #[Test]
    public function custom_hard_signals_flag_on_their_own_and_run_before_the_exemption(): void
    {
        User::query()->create(['email' => 'customer@example.com']);
        FormShield::extendHard('banned_word', static fn (Submission $s): bool => str_contains($s->string('message'), 'viagra'));

        $verdict = $this->inspect($this->humanPayload(['email' => 'customer@example.com', 'message' => 'cheap viagra']));

        self::assertSame('banned_word', $verdict->reason);
    }

    #[Test]
    public function custom_deferred_signals_run_last(): void
    {
        $calls = 0;

        FormShield::extend('slow_lookup', new class($calls) implements DeferredSignal
        {
            public function __construct(private int &$calls) {}

            public function name(): string
            {
                return 'slow_lookup';
            }

            public function fires(Submission $submission, Profile $profile): bool
            {
                $this->calls++;

                return true;
            }
        }, weight: 1);

        $this->inspect($this->humanPayload(['_fs_js' => '', 'name' => 'http://x.test']));
        self::assertSame(0, $calls);

        $verdict = $this->inspect($this->humanPayload(['message' => 'Fresh message']));
        self::assertSame(1, $calls);
        self::assertSame(['slow_lookup' => 1], $verdict->signals);
    }

    #[Test]
    public function signals_can_be_forgotten(): void
    {
        FormShield::forget('no_user_agent');

        self::assertSame([], $this->inspect($this->humanPayload(), userAgent: null)->signals);
    }

    #[Test]
    public function inspected_and_quarantined_events_are_dispatched(): void
    {
        Event::fake([SubmissionInspected::class, SubmissionQuarantined::class]);

        $this->inspect($this->humanPayload());
        Event::assertDispatchedTimes(SubmissionInspected::class, 1);
        Event::assertNotDispatched(SubmissionQuarantined::class);

        $this->inspect($this->humanPayload(['fax_number' => 'x']));
        Event::assertDispatched(SubmissionQuarantined::class, static fn (SubmissionQuarantined $e): bool => $e->verdict->reason === 'honeypot');
    }

    #[Test]
    public function events_can_be_disabled(): void
    {
        Event::fake();
        config()->set('form-shield.events', false);

        $this->inspect($this->humanPayload(['fax_number' => 'x']));

        Event::assertNothingDispatched();
    }

    #[Test]
    public function hostile_input_shapes_never_throw(): void
    {
        $verdict = $this->inspect([
            'email' => ['a@b.c'],
            'message' => ['nested' => ['deep']],
            'name' => 12345,
            '_fs_started' => ['x'],
            '_fs_js' => null,
        ]);

        self::assertSame('no_token', $verdict->reason);
    }
}
