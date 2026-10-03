<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Illuminate\Http\Request;
use Itxshakil\FormShield\Submission;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubmissionTest extends TestCase
{
    #[Test]
    public function it_reads_non_strings_as_empty_instead_of_casting(): void
    {
        $submission = Submission::fromArray(['email' => ['x@y.z'], 'age' => 42, 'name' => '  Ann  ']);

        self::assertSame('', $submission->string('email'));
        self::assertSame('', $submission->string('age'));
        self::assertSame('', $submission->string('missing'));
        self::assertSame('Ann', $submission->string('name'));
    }

    #[Test]
    public function first_returns_the_first_non_empty_key(): void
    {
        $submission = Submission::fromArray(['message' => ' ', 'remarks' => 'Hi there']);

        self::assertSame('Hi there', $submission->first(['message', 'remarks', 'body']));
        self::assertSame('', $submission->first(['nope']));
    }

    #[Test]
    public function filled_treats_arrays_and_scalars_as_filled(): void
    {
        $submission = Submission::fromArray(['a' => ['x'], 'b' => '', 'c' => '  ', 'd' => 0, 'e' => []]);

        self::assertTrue($submission->filled('a'));
        self::assertFalse($submission->filled('b'));
        self::assertFalse($submission->filled('c'));
        self::assertTrue($submission->filled('d'));
        self::assertFalse($submission->filled('e'));
    }

    #[Test]
    public function it_captures_ip_and_user_agent_from_a_request(): void
    {
        $request = Request::create('/contact', 'POST', ['email' => 'a@b.co'], server: [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);

        $submission = Submission::fromRequest($request);

        self::assertSame('203.0.113.9', $submission->ip());
        self::assertSame('Mozilla/5.0', $submission->userAgent());
        self::assertSame(['email'], $submission->keys());
    }
}
