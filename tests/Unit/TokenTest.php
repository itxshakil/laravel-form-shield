<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Unit;

use Illuminate\Encryption\Encrypter;
use Itxshakil\FormShield\Token;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    #[Test]
    public function it_round_trips_a_timestamp(): void
    {
        $token = new Token(new Encrypter(str_repeat('a', 32), 'aes-256-cbc'));

        self::assertSame(1_700_000_000, $token->read($token->mint(1_700_000_000)));
    }

    #[Test]
    public function forged_or_foreign_tokens_read_as_null(): void
    {
        $token = new Token(new Encrypter(str_repeat('a', 32), 'aes-256-cbc'));
        $other = new Token(new Encrypter(str_repeat('b', 32), 'aes-256-cbc'));

        self::assertNull($token->read(''));
        self::assertNull($token->read('1700000000'));
        self::assertNull($token->read($other->mint(1_700_000_000)));
        self::assertNull($token->read((new Encrypter(str_repeat('a', 32), 'aes-256-cbc'))->encryptString('not-a-number')));
    }
}
