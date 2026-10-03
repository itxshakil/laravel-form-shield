# Contributing

Thanks for helping. Issues and pull requests are welcome. Please follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Setup

```bash
git clone https://github.com/itxshakil/laravel-form-shield.git
cd laravel-form-shield
composer install
npm install
```

## Before opening a PR

```bash
composer test        # PHPUnit
npm test             # the shield script and client helpers
composer analyse     # PHPStan, level max
composer format      # Pint (composer lint checks without fixing)
composer qa          # lint + analyse + test
```

All of these run in CI against PHP 8.2–8.5 and Laravel 12 and 13.

## Ground rules

- **Every default soft weight stays below the threshold.** A test enforces it. No single signal may quarantine a real person on its own.
- **New signals need innocent explanations.** List them in `docs/signals.md`. If you have precision numbers from real traffic (`form-shield:report`), include them in the PR. They matter more than the code.
- **Read input through `Submission`.** Never cast request values. Bots post arrays where strings belong on purpose.
- **No network calls in non-deferred signals.**
- **No new runtime dependencies** without discussing it in an issue first.
- **The detector's record is append-only.** Nothing may overwrite `spam_reason`, `spam_score` or `spam_signals` after the first write.

## Commit messages and changelog

Write commit messages in the imperative mood ("Add gibberish signal"), and add a line under "Unreleased" in `CHANGELOG.md`.
