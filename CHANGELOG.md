# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-03

First release. It ships what was planned as 1.0, 1.1 and 1.2 in a single version.

### Core
- `FormShield::inspect()` returns a `Verdict`: clean, flagged, expired or rate limited, always with the full signal map and score.
- Hard signals: honeypot, missing or forged token, too fast. Plus an expired state for forms open past `max_age_seconds`.
- Soft signals: `no_js`, `duplicate`, `disposable_email`, `no_mx` (deferred and cached), `foreign_script`, `no_user_agent`, `url_in_name`.
- Known-sender exemption that runs after the hard signals.
- Profiles: per-form threshold, weights, input map, timing, signal set, exemption and caps.
- Custom signals: `extend()` (soft), `extendHard()`, `DeferredSignal`, `forget()`.
- `<x-form-shield />` Blade component and the `@formShieldScripts` directive (CSP-nonce aware).
- `NoSpamContent` validation rule: link count, link markup, blocked phrases.
- `HasSpamVerdict` model trait and the `$table->spamColumns()` / `dropSpamColumns()` migration macros. Labels never overwrite the detector's record.
- `form-shield:report`: per-signal precision against reviewed rows, false positives and misses.
- Testing: `FormShield::fake()` with assertions, and the `InteractsWithFormShield` trait.

### Setup and storage
- `form-shield:install`: publishes the config and asks where verdicts go (an existing table, or the built-in log). Takes `--table`, `--log`, `--migrate` and `--force` for non-interactive use.
- `form-shield:columns {table}`: writes the migration adding the spam columns to an existing table. Refuses tables that already have them and never writes a duplicate.
- Built-in submissions log (`form_shield_submissions`, `ShieldSubmission` model) for forms that don't store posts: `FormShield::record()`, `FormShield::recorded()`, optional auto-logging, encrypted payloads with redaction, polymorphic subject, prunable after `retention_days`.
- `form-shield:report` defaults to the built-in log when no model is given.
- `Model::createWithVerdict($data, $verdict)` on `HasSpamVerdict`: your data goes through `$fillable`, the verdict columns are force-filled, so they're never silently dropped.
- Enums: `VerdictStatus` (`$verdict->status`), `Reason` (built-in reasons, with `$verdict->knownReason()` and `hasReason()`), `BuiltInSignal`, `SpamSource`, `StorageOption`.
- The installer validates the table name first and exits non-zero on failure. It never runs `migrate` while a target table is missing, keeps an existing config unless `--force` is given, and offers to run migrations interactively.

### Livewire, SPAs and middleware (planned 1.1)
- `WithFormShield` Livewire trait and `<x-form-shield wire />`.
- `form-shield:{profile}` middleware and the `$request->spamVerdict()` macro.
- Optional JSON fields endpoint, plus a `client.js` helper for Inertia, Vue, React and cross-origin forms.
- New signals: `gibberish` and `unexpected_fields`.

### Rate limits and events (planned 1.2)
- Per-profile daily and global hourly caps per IP, with an IPv4/IPv6 CIDR allowlist and a `rate_limited` verdict (a 429 from the middleware).
- Events: `SubmissionInspected`, `SubmissionQuarantined`, `SubmissionReviewed`.

[Unreleased]: https://github.com/itxshakil/laravel-form-shield/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/itxshakil/laravel-form-shield/releases/tag/v1.0.0
