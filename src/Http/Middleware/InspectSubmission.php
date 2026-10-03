<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Itxshakil\FormShield\FormShield;
use Itxshakil\FormShield\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts a route behind the shield:
 *
 *     Route::post('/contact', ...)->middleware('form-shield:contact');
 *
 *     // in the controller, after validation:
 *     $verdict = $request->spamVerdict();
 *
 * Up front, the middleware applies the rate caps (429 by default) and,
 * optionally, rejects expired forms (422). The full inspection is lazy: it
 * runs on the first spamVerdict() call. That way a visitor whose submission
 * failed validation, and who resubmits the same message, isn't scored as a
 * duplicate of their own first attempt.
 *
 * Spam is never rejected here. Quarantine means the bot gets the normal
 * success response, so storing the row and skipping the side effects is the
 * controller's job.
 */
final class InspectSubmission
{
    public function __construct(
        private readonly FormShield $shield,
        private readonly Config $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $profile = 'default'): Response
    {
        $retryAfter = $this->shield->throttle($request, $profile);

        if ($retryAfter !== null && Value::bool($this->config->get('form-shield.middleware.reject_rate_limited'), true)) {
            throw new ThrottleRequestsException(
                Value::string(__('form-shield::messages.rate_limited')),
                null,
                ['Retry-After' => (string) $retryAfter],
            );
        }

        if (Value::bool($this->config->get('form-shield.middleware.reject_expired')) && $this->shield->isExpired($request, $profile)) {
            throw ValidationException::withMessages([
                $this->shield->fieldNames()['timestamp'] => Value::string(__('form-shield::messages.expired')),
            ]);
        }

        $request->attributes->set(FormShield::PROFILE_ATTRIBUTE, $profile);

        return $next($request);
    }
}
