<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * The same message from the same address twice within the window. Catches
 * one message blasted at every page that carries the form.
 *
 * Cache::add() writes only when the key is missing and reports whether it
 * did, so the check is one atomic round trip, not a has()/put() race.
 */
final class DuplicatePayload implements Signal
{
    /** Without an email, only messages at least this long are fingerprinted. */
    private const MIN_ANONYMOUS_LENGTH = 20;

    public function __construct(private readonly CacheFactory $cache) {}

    public function name(): string
    {
        return 'duplicate';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $message = $profile->message($submission);

        if ($message === '') {
            return false;
        }

        $email = $profile->email($submission);

        if ($email === '' && mb_strlen($message) < self::MIN_ANONYMOUS_LENGTH) {
            return false;
        }

        $key = 'form-shield:dup:'.$profile->name.':'.hash('xxh128', $email."\0".$message);
        $store = $profile->string('cache_store');

        return ! $this->cache->store($store === '' ? null : $store)
            ->add($key, true, $profile->int('duplicate_window_seconds', 3600));
    }
}
