<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Facades;

use Illuminate\Support\Facades\Facade;
use Itxshakil\FormShield\FormShield as Manager;

/**
 * @method static \Itxshakil\FormShield\Verdict inspect(\Illuminate\Http\Request|\Itxshakil\FormShield\Submission $submission, string $profile = 'default')
 * @method static \Itxshakil\FormShield\Verdict|null verdict(\Illuminate\Http\Request $request)
 * @method static \Itxshakil\FormShield\Models\ShieldSubmission record(\Illuminate\Http\Request|\Itxshakil\FormShield\Submission $submission, \Itxshakil\FormShield\Verdict $verdict, ?\Illuminate\Database\Eloquent\Model $subject = null)
 * @method static \Itxshakil\FormShield\Models\ShieldSubmission|null recorded(\Illuminate\Http\Request $request)
 * @method static int|null throttle(\Illuminate\Http\Request $request, string $profile = 'default')
 * @method static bool isExpired(\Illuminate\Http\Request $request, string $profile = 'default')
 * @method static array<string, string> fields()
 * @method static array{honeypot: string, timestamp: string, js: string} fieldNames()
 * @method static void extend(string $name, string|\Itxshakil\FormShield\Contracts\Signal|(\Closure(\Itxshakil\FormShield\Submission, \Itxshakil\FormShield\Profile): bool) $signal, ?int $weight = null)
 * @method static void extendHard(string $name, string|\Itxshakil\FormShield\Contracts\Signal|(\Closure(\Itxshakil\FormShield\Submission, \Itxshakil\FormShield\Profile): bool) $signal)
 * @method static void forget(string $name)
 * @method static \Itxshakil\FormShield\SignalRegistry signals()
 * @method static \Itxshakil\FormShield\Profile profile(string $name = 'default')
 * @method static \Itxshakil\FormShield\Testing\FormShieldFake fake(?\Itxshakil\FormShield\Verdict $verdict = null)
 *
 * @see Manager
 */
final class FormShield extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
