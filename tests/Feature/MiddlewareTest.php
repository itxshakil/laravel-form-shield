<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('form-shield.profiles.contact', []);

        Route::post('/contact', static function (Request $request) {
            $verdict = $request->spamVerdict();

            return response()->json(['verdict' => $verdict?->toArray()]);
        })->middleware('form-shield:contact');

        Route::post('/plain', static fn (Request $request) => response()->json(['verdict' => $request->spamVerdict()?->toArray()]))
            ->middleware('form-shield');

        Route::post('/validated', static function (Request $request) {
            $request->validate(['email' => ['required', 'email']]);

            return response()->json(['verdict' => $request->spamVerdict()?->toArray()]);
        })->middleware('form-shield:contact');

        Route::post('/twice', static function (Request $request) {
            $first = $request->spamVerdict();
            $second = FormShield::inspect($request, 'contact');

            return response()->json(['same' => $first === $second, 'verdict' => $second->toArray()]);
        })->middleware('form-shield:contact');
    }

    #[Test]
    public function the_verdict_is_attached_to_the_request(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->postJson('/contact', $this->humanPayload())
            ->assertOk()
            ->assertJsonPath('verdict.is_spam', false)
            ->assertJsonPath('verdict.profile', 'contact');

        $this->postJson('/plain', $this->humanPayload(['message' => 'Other message']))
            ->assertJsonPath('verdict.profile', 'default');
    }

    #[Test]
    public function spam_passes_through_so_the_controller_can_quarantine_it(): void
    {
        $this->postJson('/contact', $this->humanPayload(['fax_number' => 'bot']))
            ->assertOk()
            ->assertJsonPath('verdict.is_spam', true)
            ->assertJsonPath('verdict.reason', 'honeypot');
    }

    #[Test]
    public function rate_limited_senders_get_a_429_with_retry_after(): void
    {
        config()->set('form-shield.rate_limits.per_ip_per_day', 1);

        $this->postJson('/contact', $this->humanPayload())->assertOk();

        $this->postJson('/contact', $this->humanPayload(['message' => 'again']))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function rate_limited_verdicts_can_be_passed_through_instead(): void
    {
        config()->set('form-shield.rate_limits.per_ip_per_day', 1);
        config()->set('form-shield.middleware.reject_rate_limited', false);

        $this->postJson('/contact', $this->humanPayload());

        $this->postJson('/contact', $this->humanPayload(['message' => 'again']))
            ->assertOk()
            ->assertJsonPath('verdict.is_rate_limited', true);
    }

    #[Test]
    public function expired_forms_pass_through_by_default_and_can_be_rejected(): void
    {
        $this->postJson('/contact', $this->humanPayload(secondsAgo: 50000))
            ->assertOk()
            ->assertJsonPath('verdict.is_expired', true);

        config()->set('form-shield.middleware.reject_expired', true);

        $this->postJson('/contact', $this->humanPayload(secondsAgo: 50000))
            ->assertStatus(422)
            ->assertJsonValidationErrors('_fs_started');
    }

    #[Test]
    public function inspection_is_lazy_so_a_corrected_resubmission_is_not_a_duplicate(): void
    {
        $payload = $this->humanPayload(['email' => 'not-an-email']);

        $this->postJson('/validated', $payload)->assertStatus(422);

        $this->postJson('/validated', [...$payload, 'email' => 'jane@example.com'])
            ->assertOk()
            ->assertJsonPath('verdict.signals', []);
    }

    #[Test]
    public function inspecting_a_request_twice_returns_the_first_verdict(): void
    {
        $this->postJson('/twice', $this->humanPayload())
            ->assertOk()
            ->assertJsonPath('same', true)
            ->assertJsonPath('verdict.signals', []);
    }

    #[Test]
    public function the_controller_inspection_does_not_count_against_caps_twice(): void
    {
        config()->set('form-shield.rate_limits.per_ip_per_day', 2);

        $this->postJson('/twice', $this->humanPayload(['message' => 'one']))->assertOk();
        $this->postJson('/twice', $this->humanPayload(['message' => 'two']))->assertOk();
        $this->postJson('/twice', $this->humanPayload(['message' => 'three']))->assertStatus(429);
    }

    #[Test]
    public function an_unknown_profile_fails_loudly(): void
    {
        Route::post('/typo', static fn () => 'x')->middleware('form-shield:contcat');

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);

        $this->post('/typo', $this->humanPayload());
    }
}
