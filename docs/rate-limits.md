# Rate limits

Caps are off by default. Turn them on in the config, or per profile:

```php
'rate_limits' => [
    'per_ip_per_day' => 10,           // per profile
    'global_per_ip_per_hour' => 30,   // across every profile
    'allowlist' => ['10.0.0.0/8', '203.0.113.4', '2001:db8::/32'],
],
```

Or set `FORM_SHIELD_IP_ALLOWLIST=10.0.0.0/8,203.0.113.4`.

## Behaviour

- Every inspection counts as an attempt, clean or spam. A flood is a flood either way.
- A sender over a cap gets a `rate_limited` verdict (`$verdict->isRateLimited`, `$verdict->retryAfter` in seconds). It isn't spam, so storing it as spam would pollute the report.
- With the middleware, rate-limited requests get a `429` with `Retry-After`. Set `middleware.reject_rate_limited` to `false` to handle them yourself.
- Allowlisted IPs and CIDR ranges (IPv4 and IPv6) skip both caps. Everything else still applies to them.
- Counters use your default cache store through Laravel's `RateLimiter`. Use a shared store (Redis, database) when you run several servers.

## Behind a proxy

Caps key on `$request->ip()`. Behind a load balancer or Cloudflare, configure Laravel's [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies), or every visitor shares the proxy's IP and one cap.
