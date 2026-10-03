<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Signals;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;
use Itxshakil\FormShield\Support\EmailDomain;

/** The email's domain, or a parent of it, is on the disposable list. */
final class DisposableEmail implements Signal
{
    /** @var array<string, array<string, true>> */
    private array $fileCache = [];

    public function name(): string
    {
        return 'disposable_email';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $domain = EmailDomain::of($profile->email($submission));

        if ($domain === null) {
            return false;
        }

        $listed = $this->domains($profile);

        // mail.mailinator.com is as disposable as mailinator.com.
        $parts = explode('.', $domain);

        while (count($parts) >= 2) {
            if (isset($listed[implode('.', $parts)])) {
                return true;
            }

            array_shift($parts);
        }

        return false;
    }

    /** @return array<string, true> */
    private function domains(Profile $profile): array
    {
        $domains = array_fill_keys(array_map(
            static fn (string $domain): string => mb_strtolower(trim($domain)),
            $profile->strings('disposable_domains'),
        ), true);

        $file = $profile->string('disposable_domains_file');

        if ($file !== '') {
            $domains += $this->fileCache[$file] ??= $this->readFile($file);
        }

        unset($domains['']);

        return $domains;
    }

    /** @return array<string, true> */
    private function readFile(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        $domains = [];

        foreach ($lines as $line) {
            $line = mb_strtolower(trim($line));

            if ($line !== '' && ! str_starts_with($line, '#')) {
                $domains[$line] = true;
            }
        }

        return $domains;
    }
}
