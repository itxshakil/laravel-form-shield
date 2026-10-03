<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Itxshakil\FormShield\Contracts\DeferredSignal;
use Itxshakil\FormShield\Contracts\MxResolver;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\EmailDomain;

/**
 * The email's domain has no MX record, so nobody can receive mail there.
 *
 * Deferred because it's the only check that blocks on the network, and PHP's
 * resolver has no timeout. A dead nameserver mustn't stall a submission that
 * is already over the threshold. Cached per domain, otherwise every
 * gmail.com submission would resolve gmail.com again.
 */
final class NoMxRecord implements DeferredSignal
{
    private const CACHE_SECONDS = 86400;

    public function __construct(
        private readonly MxResolver $resolver,
        private readonly CacheFactory $cache,
    ) {}

    public function name(): string
    {
        return 'no_mx';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        if (! $profile->bool('email_dns_check', true)) {
            return false;
        }

        $domain = EmailDomain::of($profile->email($submission));

        if ($domain === null) {
            return false;
        }

        $store = $profile->string('cache_store');

        return ! $this->cache->store($store === '' ? null : $store)->remember(
            'form-shield:mx:'.$domain,
            self::CACHE_SECONDS,
            fn (): bool => $this->resolver->hasMxRecord($domain),
        );
    }
}
