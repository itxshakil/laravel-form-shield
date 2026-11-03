<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Itxshakil\FormShield\Rules\NoSpamContent;
use Itxshakil\FormShield\Support\Content;
use Itxshakil\FormShield\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ContentTest extends TestCase
{
    #[Test]
    public function it_counts_links(): void
    {
        self::assertSame(0, Content::links('No links here, just words.'));
        self::assertSame(3, Content::links('https://a.test http://b.test and https://c.test'));
        self::assertSame(1, Content::links('Visit www.example.com today'));
        self::assertSame(2, Content::links('HTTPS://A.TEST www.b.test'));
    }

    #[Test]
    public function it_detects_link_markup(): void
    {
        self::assertTrue(Content::hasMarkup('<a href="https://x.test">click</a>'));
        self::assertTrue(Content::hasMarkup('[url=https://x.test]click[/url]'));
        self::assertTrue(Content::hasMarkup('[URL]https://x.test[/URL]'));
        self::assertTrue(Content::hasMarkup('[link=https://x.test]click[/link]'));
        self::assertFalse(Content::hasMarkup('A plain https://x.test address, and an <b>emphasis</b>.'));
    }

    #[Test]
    public function a_phrase_match_is_case_insensitive_and_returns_the_phrase(): void
    {
        $phrases = ['Quality Backlinks', 'rank your website'];

        self::assertSame('Quality Backlinks', Content::blockedPhrase('We sell QUALITY BACKLINKS cheaply', $phrases));
        self::assertSame('rank your website', Content::blockedPhrase('Let us Rank Your Website', $phrases));
        self::assertNull(Content::blockedPhrase('A perfectly ordinary message', $phrases));
    }

    #[Test]
    public function blank_phrases_never_match(): void
    {
        self::assertNull(Content::blockedPhrase('anything', ['', '  ']));
        self::assertNull(Content::blockedPhrase('anything', []));
    }

    /** @return iterable<string, array{string}> */
    public static function messages(): iterable
    {
        yield 'plain' => ['Can you move my WordPress site to your servers?'];
        yield 'two links' => ['See https://a.example and www.b.example for details.'];
        yield 'three links' => ['https://a.test https://b.test https://c.test'];
        yield 'anchor markup' => ['<a href="https://x.test">click</a>'];
        yield 'bbcode' => ['[url=https://x.test]click[/url]'];
        yield 'phrase' => ['We provide Quality Backlinks for your site.'];
    }

    #[Test]
    #[DataProvider('messages')]
    public function no_spam_content_and_the_signals_agree(string $message): void
    {
        $limit = 2;
        $phrases = ['quality backlinks'];

        $ruleRejects = Validator::make(
            ['message' => $message],
            ['message' => [NoSpamContent::make()->maxLinks($limit)->phrases($phrases)]],
        )->fails();

        $helpersFlag = Content::links($message) > $limit
            || Content::hasMarkup($message)
            || Content::blockedPhrase($message, $phrases) !== null;

        self::assertSame($helpersFlag, $ruleRejects);
    }
}
