# Releasing

## Before the first release

1. On your machine: `composer install && npm install`, then `composer qa && npm test`. The suite was developed against a stand-in for Orchestra Testbench, so this is the first run against the real one. Fix anything it finds.
2. Create `github.com/itxshakil/laravel-form-shield` (public, no README, licence or .gitignore, since they're already here), push `main`, and wait for every CI job to go green: Pint, PHPStan, the Composer validation, the PHP × Laravel matrix (prefer-lowest and highest), and the JS tests.
3. In the repo settings, add the topics: `laravel`, `laravel-package`, `spam-protection`, `honeypot`, `captcha-alternative`, `anti-spam`, `livewire`, `php`.
4. Create these labels so the labeler and release-drafter work: `core`, `signals`, `integrations`, `console`, `config`, `events`, `tests`, `documentation`, `ci`, `tooling`, `dependencies`, `meta`, `breaking-change`, `skip-changelog`, `question`.

## One-time repo settings (keeps Dependabot hands-off)

Run once, with the `gh` CLI logged in with the `workflow` scope (`gh auth refresh -h github.com -s workflow && gh auth setup-git`):

```bash
gh repo edit --enable-auto-merge --delete-branch-on-merge
gh api -X PUT repos/itxshakil/laravel-form-shield/branches/main/protection --input - <<'JSON'
{
  "required_status_checks": { "strict": false, "contexts": ["CI passed"] },
  "enforce_admins": false,
  "required_pull_request_reviews": null,
  "restrictions": null
}
JSON
```

After that, Dependabot's monthly PRs for GitHub Actions and the npm test tooling merge themselves when CI is green. `enforce_admins: false` means you can still push straight to `main`.

## Each release

1. Make sure CI on `main` is green.
2. Move `## [Unreleased]` entries in `CHANGELOG.md` under a dated version heading and commit.
3. Tag and push:

    ```bash
    git tag -a v1.0.0 -m "v1.0.0"
    git push origin v1.0.0
    ```

4. The Release workflow checks that the tag installs cleanly without dev dependencies. Then publish the draft release that release-drafter prepared, or run `gh release create v1.0.0 --notes-from-tag`.
5. First release only: submit the repository at <https://packagist.org/packages/submit>, then check the GitHub hook is active (Packagist → your package → Settings), so new tags appear automatically.

## Versioning

- Patch: bug fixes, docs, new disposable domains.
- Minor: new signals (off by default, or with a weight that can't quarantine on its own), new options, new integrations.
- Major: anything that changes a default verdict for existing traffic, renames a config key, or changes the stored columns.

Changing a default weight or the threshold changes verdicts for every installed app, so it's at least a minor version with a CHANGELOG note. Treat it as breaking if the change makes quarantining more likely.
