<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Itxshakil\FormShield\Signals\Gibberish;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GibberishTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function words(): iterable
    {
        yield 'keyboard mash' => ['dfghjkls', true];
        yield 'case noise' => ['xKqLmPzRw', true];
        yield 'random caps' => ['AbCdEfGhIj', true];
        yield 'strengths' => ['strengths', false];
        yield 'McDonald' => ['McDonald', false];
        yield 'Alexandria' => ['Alexandria', false];
        yield 'WordPress' => ['WordPress', false];
        yield 'ALLCAPSWORD' => ['HOSTINGPLAN', false];
        yield 'JavaScript' => ['JavaScript', false];
    }

    #[Test]
    #[DataProvider('words')]
    public function it_judges_single_words(string $word, bool $expected): void
    {
        self::assertSame($expected, (new Gibberish)->isGibberish($word));
    }
}
