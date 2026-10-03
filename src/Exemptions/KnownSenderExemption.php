<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Exemptions;

use Illuminate\Database\Eloquent\Model;
use Itxshakil\FormShield\Contracts\SenderExemption;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * An existing customer is never scored. They're the person this must never
 * drop silently, and the soft tier is where the false positives live.
 */
final class KnownSenderExemption implements SenderExemption
{
    public function name(): string
    {
        return 'known_sender';
    }

    public function exempts(Submission $submission, Profile $profile): bool
    {
        if (! $profile->bool('exemption.enabled')) {
            return false;
        }

        $model = $profile->get('exemption.model');
        $column = $profile->string('exemption.column', 'email');
        $email = $profile->email($submission);

        if ($email === '' || ! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return false;
        }

        return $model::query()->where($column, $email)->exists();
    }
}
