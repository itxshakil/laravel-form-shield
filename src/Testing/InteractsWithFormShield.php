<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Testing;

use Illuminate\Support\Carbon;
use Itxshakil\FormShield\FormShield;
use Itxshakil\FormShield\Token;

/**
 * For tests that post a protected form. Without these fields the inspector
 * sees no token and quarantines the submission, quietly, by design.
 *
 *     $this->post('/contact', [...$data, ...$this->formShieldFields()]);
 *
 * The timestamp is minted in the past, so there's nothing to sleep() for.
 */
trait InteractsWithFormShield
{
    /** @return array<string, string> */
    protected function formShieldFields(int $secondsAgo = 10, bool $withJs = true): array
    {
        $token = app(Token::class)->mint(Carbon::now()->getTimestamp() - $secondsAgo);
        $names = app(FormShield::class)->fieldNames();

        return [
            $names['timestamp'] => $token,
            $names['js'] => $withJs ? $token : '',
        ];
    }
}
