<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Itxshakil\FormShield\Tests\Concerns\CreatesTables;
use Itxshakil\FormShield\Tests\Fixtures\Inquiry;
use Itxshakil\FormShield\Tests\Fixtures\User;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\Attributes\Test;

final class ReportCommandTest extends TestCase
{
    use CreatesTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    private function store(Verdict $verdict, ?bool $reviewAs = null, ?Carbon $at = null): Inquiry
    {
        $inquiry = Inquiry::createWithVerdict([], $verdict);

        if ($at !== null) {
            $inquiry->forceFill(['created_at' => $at])->save();
        }

        if ($reviewAs === true) {
            $inquiry->markAsSpam('admin');
        } elseif ($reviewAs === false) {
            $inquiry->markAsHam('admin');
        }

        return $inquiry;
    }

    private function report(string $model = Inquiry::class, array $options = []): string
    {
        Artisan::call('form-shield:report', ['model' => $model, ...$options]);

        return Artisan::output();
    }

    #[Test]
    public function it_measures_precision_against_reviewed_rows_only(): void
    {
        // no_js fired 4 times: 2 reviewed spam, 1 reviewed ham, 1 unreviewed.
        $this->store(Verdict::flagged('score', ['no_js' => 2, 'duplicate' => 3], 5), true);
        $this->store(Verdict::flagged('score', ['no_js' => 2, 'url_in_name' => 3], 5), true);
        $this->store(Verdict::flagged('score', ['no_js' => 2, 'disposable_email' => 2], 4), false);
        $this->store(Verdict::clean(['no_js' => 2], 2));

        // Hard flag, confirmed.
        $this->store(Verdict::flagged('honeypot'), true);

        // A miss: passed, then marked spam.
        $this->store(Verdict::clean(), true);

        // Known-sender marker must not appear as a signal.
        $this->store(Verdict::clean(['known_sender' => 0]));

        $output = $this->report();

        self::assertMatchesRegularExpression('/no_js\s*\|\s*2\s*\|\s*4\s*\|\s*3\s*\|\s*1\s*\|\s*66\.7%/', $output);
        self::assertMatchesRegularExpression('/honeypot\s*\|\s*hard\s*\|\s*1\s*\|\s*1\s*\|\s*0\s*\|\s*100\.0%/', $output);
        self::assertMatchesRegularExpression('/disposable_email\s*\|\s*2\s*\|\s*1\s*\|\s*1\s*\|\s*1\s*\|\s*0\.0%/', $output);
        self::assertStringNotContainsString('known_sender', $output);

        self::assertMatchesRegularExpression('/Quarantined:\s+4/', $output);
        self::assertMatchesRegularExpression('/Reviewed:\s+5/', $output);
        self::assertMatchesRegularExpression('/False positives:\s+1/', $output);
        self::assertMatchesRegularExpression('/Missed spam:\s+1/', $output);
    }

    #[Test]
    public function it_respects_the_days_window(): void
    {
        $this->store(Verdict::flagged('honeypot'), true, Carbon::now()->subDays(40));

        self::assertStringContainsString('No submissions in the last 30 days', $this->report());
        self::assertStringContainsString('honeypot', $this->report(options: ['--days' => 60]));
    }

    #[Test]
    public function it_warns_when_nothing_has_been_reviewed(): void
    {
        $this->store(Verdict::flagged('score', ['no_js' => 2, 'duplicate' => 3], 5));

        self::assertStringContainsString('No reviewed rows yet', $this->report());
    }

    #[Test]
    public function it_rejects_models_without_the_trait(): void
    {
        self::assertSame(1, Artisan::call('form-shield:report', ['model' => User::class]));
        self::assertSame(1, Artisan::call('form-shield:report', ['model' => 'App\\Nope']));
    }
}
