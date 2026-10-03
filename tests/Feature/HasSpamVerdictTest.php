<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Itxshakil\FormShield\Enums\SpamSource;
use Itxshakil\FormShield\Events\SubmissionReviewed;
use Itxshakil\FormShield\Tests\Concerns\CreatesTables;
use Itxshakil\FormShield\Tests\Fixtures\Inquiry;
use Itxshakil\FormShield\Tests\Fixtures\User;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

final class HasSpamVerdictTest extends TestCase
{
    use CreatesTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    #[Test]
    public function the_macro_adds_every_column(): void
    {
        foreach (['is_spam', 'spam_reason', 'spam_score', 'spam_signals', 'spam_source', 'reviewed_at', 'reviewed_by'] as $column) {
            self::assertTrue(Schema::hasColumn('inquiries', $column), "Missing {$column}");
        }
    }

    #[Test]
    public function the_drop_macro_removes_them(): void
    {
        Schema::table('inquiries', static fn (Blueprint $table) => $table->dropSpamColumns());

        self::assertFalse(Schema::hasColumn('inquiries', 'is_spam'));
        self::assertFalse(Schema::hasColumn('inquiries', 'reviewed_by'));
        self::assertTrue(Schema::hasColumn('inquiries', 'email'));
    }

    #[Test]
    public function a_verdict_is_stored_with_casts(): void
    {
        $inquiry = Inquiry::createWithVerdict(
            ['email' => 'a@b.co'],
            Verdict::flagged('score', ['no_js' => 2, 'duplicate' => 3], 5),
        )->fresh();

        self::assertTrue($inquiry->is_spam);
        self::assertSame(5, $inquiry->spam_score);
        self::assertSame(['no_js' => 2, 'duplicate' => 3], $inquiry->spam_signals);
        self::assertSame(SpamSource::Detector, $inquiry->spam_source);
        self::assertTrue($inquiry->detectorFlagged());
        self::assertFalse($inquiry->isReviewed());
    }

    #[Test]
    public function apply_verdict_fills_without_saving(): void
    {
        $inquiry = (new Inquiry(['email' => 'a@b.co']))->applyVerdict(Verdict::flagged('honeypot'));

        self::assertFalse($inquiry->exists);
        self::assertTrue($inquiry->is_spam);
        self::assertSame('honeypot', $inquiry->spam_reason);
    }

    #[Test]
    public function marking_as_ham_keeps_what_the_detector_thought(): void
    {
        Event::fake([SubmissionReviewed::class]);
        $reviewer = User::query()->create(['email' => 'admin@example.com']);

        $inquiry = Inquiry::createWithVerdict([], Verdict::flagged('score', ['no_js' => 2, 'duplicate' => 3], 5));
        $inquiry->markAsHam($reviewer);
        $inquiry = $inquiry->fresh();

        self::assertFalse($inquiry->is_spam);
        self::assertSame(SpamSource::Reviewer, $inquiry->spam_source);
        self::assertSame((string) $reviewer->id, $inquiry->reviewed_by);
        self::assertNotNull($inquiry->reviewed_at);

        // The detector's record is untouched.
        self::assertSame('score', $inquiry->spam_reason);
        self::assertSame(5, $inquiry->spam_score);
        self::assertSame(['no_js' => 2, 'duplicate' => 3], $inquiry->spam_signals);

        self::assertTrue($inquiry->isFalsePositive());
        self::assertFalse($inquiry->isMissedSpam());

        Event::assertDispatched(SubmissionReviewed::class, static fn (SubmissionReviewed $e): bool => ! $e->isSpam && $e->wasSpam && $e->changedVerdict());
    }

    #[Test]
    public function marking_a_passed_submission_as_spam_records_a_miss(): void
    {
        $inquiry = Inquiry::createWithVerdict([], Verdict::clean(['no_js' => 2], 2));
        $inquiry->markAsSpam('moderator-7');

        self::assertTrue($inquiry->is_spam);
        self::assertSame('moderator-7', $inquiry->reviewed_by);
        self::assertTrue($inquiry->isMissedSpam());
        self::assertFalse($inquiry->isFalsePositive());
    }

    #[Test]
    public function scopes_filter_by_effective_verdict_and_review_state(): void
    {
        Inquiry::createWithVerdict([], Verdict::flagged('honeypot'));
        Inquiry::createWithVerdict([], Verdict::clean());
        Inquiry::createWithVerdict([], Verdict::clean())->markAsSpam();

        self::assertSame(2, Inquiry::query()->spam()->count());
        self::assertSame(1, Inquiry::query()->notSpam()->count());
        self::assertSame(1, Inquiry::query()->reviewed()->count());
        self::assertSame(2, Inquiry::query()->unreviewed()->count());
    }

    #[Test]
    public function spreading_to_attributes_into_create_loses_the_verdict_on_a_fillable_model(): void
    {
        // The pitfall createWithVerdict() exists for: $fillable silently drops the columns.
        $inquiry = Inquiry::query()->create(['email' => 'a@b.co', ...Verdict::flagged('honeypot')->toAttributes()])->fresh();

        self::assertFalse($inquiry?->is_spam);
        self::assertNull($inquiry?->spam_reason);
    }

    #[Test]
    public function create_with_verdict_respects_fillable_for_data_and_saves_the_verdict(): void
    {
        $inquiry = Inquiry::createWithVerdict(
            ['email' => 'a@b.co', 'message' => 'Hi', 'is_admin' => true],
            Verdict::flagged('honeypot'),
        )->fresh();

        self::assertNotNull($inquiry);
        self::assertTrue($inquiry->is_spam);
        self::assertSame('honeypot', $inquiry->spam_reason);
        self::assertSame('Hi', $inquiry->message);
        self::assertArrayNotHasKey('is_admin', $inquiry->getAttributes());
    }

    #[Test]
    public function create_with_verdict_works_under_strict_mode(): void
    {
        Model::preventSilentlyDiscardingAttributes();

        try {
            $inquiry = Inquiry::createWithVerdict(['email' => 'a@b.co'], Verdict::flagged('too_fast'));
            self::assertTrue($inquiry->fresh()?->is_spam);
        } finally {
            Model::preventSilentlyDiscardingAttributes(false);
        }
    }

    #[Test]
    public function apply_verdict_bypasses_fillable(): void
    {
        $inquiry = Inquiry::make(['email' => 'a@b.co'])->applyVerdict(Verdict::flagged('honeypot'));
        $inquiry->save();

        self::assertTrue($inquiry->fresh()?->is_spam);
    }
}
