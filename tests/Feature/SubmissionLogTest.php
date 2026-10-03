<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Models\ShieldSubmission;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Tests\Concerns\CreatesTables;
use Itxshakil\FormShield\Tests\Fixtures\Inquiry;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

final class SubmissionLogTest extends TestCase
{
    use CreatesTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        (require __DIR__.'/../../database/migrations/create_form_shield_submissions_table.php.stub')->up();

        config()->set('form-shield.profiles.contact', []);
    }

    #[Test]
    public function record_stores_the_verdict_and_a_cleaned_payload(): void
    {
        $request = Request::create('/contact', 'POST', $this->humanPayload([
            '_token' => 'csrf',
            'password' => 'hunter2',
            'Card_Number' => '4242',
            'tags' => ['a', 'b'],
        ]), server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Mozilla/5.0']);

        $verdict = FormShield::inspect($request, 'contact');
        $record = FormShield::record($request, $verdict)->fresh();

        self::assertNotNull($record);
        self::assertSame('contact', $record->profile);
        self::assertSame('jane@example.com', $record->email);
        self::assertSame('203.0.113.9', $record->ip);
        self::assertSame('Mozilla/5.0', $record->user_agent);
        self::assertFalse($record->is_spam);
        self::assertSame(['name', 'email', 'message', 'tags'], array_keys($record->payload ?? []));
        self::assertSame(['a', 'b'], $record->payload['tags'] ?? null);
    }

    #[Test]
    public function the_payload_is_encrypted_at_rest_by_default(): void
    {
        FormShield::record(Submission::fromArray(['message' => 'secret-content']), Verdict::clean());

        $raw = (string) DB::table('form_shield_submissions')->value('payload');

        self::assertStringNotContainsString('secret-content', $raw);
        self::assertSame(['message' => 'secret-content'], ShieldSubmission::query()->sole()->payload);
    }

    #[Test]
    public function encryption_can_be_turned_off(): void
    {
        config()->set('form-shield.store.encrypt_payload', false);

        FormShield::record(Submission::fromArray(['message' => 'plain']), Verdict::clean());

        self::assertStringContainsString('plain', (string) DB::table('form_shield_submissions')->value('payload'));
    }

    #[Test]
    public function recording_the_same_request_twice_returns_one_row_and_can_attach_a_subject(): void
    {
        $request = Request::create('/contact', 'POST', $this->humanPayload());
        $verdict = FormShield::inspect($request);

        $first = FormShield::record($request, $verdict);
        $inquiry = Inquiry::query()->create(['email' => 'jane@example.com']);
        $second = FormShield::record($request, $verdict, $inquiry);

        self::assertTrue($first->is($second));
        self::assertSame(1, ShieldSubmission::query()->count());
        self::assertTrue($second->fresh()?->subject?->is($inquiry));
        self::assertSame($first, FormShield::recorded($request));
    }

    #[Test]
    public function auto_store_logs_inspections_but_not_floods_or_expired_forms(): void
    {
        config()->set('form-shield.store.auto', true);
        config()->set('form-shield.rate_limits.per_ip_per_day', 2);

        $submit = fn (array $overrides = [], int $ago = 30): Verdict => FormShield::inspect(
            Submission::fromArray($this->humanPayload($overrides, $ago), '198.51.100.1', 'Mozilla/5.0'),
        );

        $submit();
        $submit(['fax_number' => 'bot', 'message' => 'different']);
        self::assertTrue($submit(['message' => 'third'])->isRateLimited);

        config()->set('form-shield.rate_limits.per_ip_per_day', null);
        self::assertTrue($submit(['message' => 'old'], 50000)->isExpired);

        self::assertSame(2, ShieldSubmission::query()->count());
        self::assertSame(1, ShieldSubmission::query()->spam()->count());
    }

    #[Test]
    public function auto_store_exposes_the_row_on_the_request(): void
    {
        config()->set('form-shield.store.auto', true);

        Route::post('/contact', static function (Request $request) {
            FormShield::inspect($request, 'contact');

            return response()->json(['id' => FormShield::recorded($request)?->id]);
        });

        $this->postJson('/contact', $this->humanPayload())->assertJsonPath('id', 1);
    }

    #[Test]
    public function the_report_defaults_to_the_built_in_log(): void
    {
        FormShield::record(Submission::fromArray([]), Verdict::flagged('honeypot'))->markAsSpam('admin');

        Artisan::call('form-shield:report');

        self::assertMatchesRegularExpression('/honeypot\s*\|\s*hard\s*\|\s*1\s*\|\s*1\s*\|\s*0\s*\|\s*100\.0%/', Artisan::output());
    }

    #[Test]
    public function the_report_explains_a_missing_table(): void
    {
        \Illuminate\Support\Facades\Schema::drop('form_shield_submissions');

        self::assertSame(1, Artisan::call('form-shield:report'));
        self::assertStringContainsString('form-shield:install', Artisan::output());
    }

    #[Test]
    public function old_unreviewed_rows_are_prunable_and_reviewed_ones_are_kept(): void
    {
        $old = FormShield::record(Submission::fromArray([]), Verdict::flagged('honeypot'));
        $reviewed = FormShield::record(Submission::fromArray([]), Verdict::flagged('honeypot'));
        $reviewed->markAsHam('admin');
        $fresh = FormShield::record(Submission::fromArray([]), Verdict::clean());

        ShieldSubmission::query()->whereKey([$old->id, $reviewed->id])->update(['created_at' => Carbon::now()->subDays(100)]);

        self::assertSame([$old->id], (new ShieldSubmission)->prunable()->pluck('id')->all());
        self::assertNotNull($fresh->id);
    }
}
