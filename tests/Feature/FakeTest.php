<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Verdict;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

final class FakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('form-shield.profiles.contact', []);

        Route::post('/contact', static fn (Request $request) => FormShield::inspect($request, 'contact')->isSpam ? 'quarantined' : 'sent');
    }

    #[Test]
    public function the_fake_passes_everything_by_default_and_records_inspections(): void
    {
        $shield = FormShield::fake();

        $this->post('/contact', ['message' => 'no shield fields at all'])->assertSee('sent');

        $shield->assertInspected('contact');
        $shield->assertInspected('contact', static fn (Submission $s): bool => $s->string('message') === 'no shield fields at all');
        $shield->assertInspectedTimes(1);
    }

    #[Test]
    public function the_fake_can_flag_everything(): void
    {
        FormShield::fake()->flagAll();

        $this->post('/contact', $this->humanPayload())->assertSee('quarantined');
    }

    #[Test]
    public function the_fake_can_return_a_fixed_verdict(): void
    {
        FormShield::fake(Verdict::expired());

        self::assertTrue(FormShield::inspect(Submission::fromArray([]))->isExpired);
    }

    #[Test]
    public function assert_nothing_inspected_fails_after_an_inspection(): void
    {
        $shield = FormShield::fake();
        $shield->assertNothingInspected();

        FormShield::inspect(Submission::fromArray([]));

        $this->expectException(AssertionFailedError::class);
        $shield->assertNothingInspected();
    }

    #[Test]
    public function inspect_attaches_the_verdict_to_the_request(): void
    {
        $request = Request::create('/x', 'POST', $this->humanPayload());

        $verdict = FormShield::inspect($request);

        self::assertSame($verdict, $request->spamVerdict());
        self::assertSame($verdict, FormShield::verdict($request));
    }
}
