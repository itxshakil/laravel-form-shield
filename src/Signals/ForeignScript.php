<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

/**
 * Most of the message's letters are in scripts your visitors don't write in.
 * Only meaningful when your audience writes in a known set of scripts; set
 * `allowed_scripts` to null to turn it off.
 */
final class ForeignScript implements Signal
{
    private const MIN_LETTERS = 10;

    private const MIN_ALLOWED_RATIO = 0.5;

    public function name(): string
    {
        return 'foreign_script';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $scripts = $profile->strings('allowed_scripts');

        if ($scripts === []) {
            return false;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $profile->message($submission)) ?? '';
        $total = mb_strlen($letters);

        if ($total < self::MIN_LETTERS) {
            return false;
        }

        $class = implode('', array_map(
            static fn (string $script): string => '\p{'.preg_replace('/[^A-Za-z_]/', '', $script).'}',
            $scripts,
        ));

        $allowed = preg_replace('/[^'.$class.']/u', '', $letters);

        if ($allowed === null) {
            // An unknown script name makes the pattern invalid. Fail open.
            return false;
        }

        return mb_strlen($allowed) / $total < self::MIN_ALLOWED_RATIO;
    }
}
