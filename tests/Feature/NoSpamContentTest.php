<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Itxshakil\FormShield\Rules\NoSpamContent;
use Itxshakil\FormShield\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class NoSpamContentTest extends TestCase
{
    private function errors(mixed $value, ?NoSpamContent $rule = null): array
    {
        return Validator::make(['message' => $value], ['message' => [$rule ?? new NoSpamContent]])->errors()->get('message');
    }

    /** @return iterable<string, array{string}> */
    public static function legitimate(): iterable
    {
        yield 'plain' => ['Can you move my WordPress site to your servers?'];
        yield 'own domain' => ['My site is https://example.com, it is slow.'];
        yield 'two links' => ['See https://a.example and www.b.example for details.'];
        yield 'seo word alone' => ['Does your hosting help with SEO?'];
    }

    #[Test]
    #[DataProvider('legitimate')]
    public function real_messages_pass(string $message): void
    {
        self::assertSame([], $this->errors($message));
    }

    #[Test]
    public function too_many_links_fail_with_the_limit_in_the_message(): void
    {
        $errors = $this->errors('https://a.test https://b.test https://c.test');

        self::assertSame(['Please include no more than 2 links.'], $errors);
    }

    #[Test]
    public function link_markup_fails(): void
    {
        self::assertNotEmpty($this->errors('<a href="https://x.test">click</a>'));
        self::assertNotEmpty($this->errors('[url=https://x.test]click[/url]'));
    }

    #[Test]
    public function blocked_phrases_fail_case_insensitively(): void
    {
        self::assertNotEmpty($this->errors('We sell QUALITY BACKLINKS cheap'));
    }

    #[Test]
    public function the_rule_can_be_tuned_inline(): void
    {
        $rule = NoSpamContent::make()->maxLinks(0)->phrases(['buy now']);

        self::assertNotEmpty($this->errors('see https://x.test', $rule));
        self::assertNotEmpty($this->errors('Buy now!', $rule));
        self::assertSame([], $this->errors('quality backlinks', $rule));
    }

    #[Test]
    public function non_strings_are_left_to_other_rules(): void
    {
        self::assertSame([], $this->errors(['x']));
    }
}
