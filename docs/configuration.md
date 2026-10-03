# Configuration

`php artisan form-shield:install` publishes the config file. To publish it on its own:

```bash
php artisan vendor:publish --tag=form-shield-config
```

Every key is documented inline in `config/form-shield.php`. The important ones:

| Key | Default | What it does |
|---|---|---|
| `fields.honeypot` | `fax_number` | Name of the honeypot input. Pick something none of your forms will ever really ask for. |
| `fields.timestamp` / `fields.js` | `_fs_started` / `_fs_js` | Names of the two hidden inputs. |
| `honeypot_class` | `null` | CSS class for the honeypot wrapper, for when your CSP forbids inline styles. Without it, an inline style moves the field off-screen. |
| `min_seconds` | `3` | Submissions faster than this are `too_fast`. |
| `max_age_seconds` | `43200` | Older forms are `expired`. |
| `threshold` | `4` | Score at which soft signals quarantine. |
| `weights` | see file | Weight per soft signal. Keep each one below the threshold. |
| `inputs` | `email`, `name`, `message` lists | Which request keys hold the email, name and free text. The first non-empty key wins. |
| `allowed_fields` | `null` | Enables the `unexpected_fields` signal. |
| `allowed_scripts` | `['Latin']` | Scripts your visitors write in. `null` turns off `foreign_script`. |
| `email_dns_check` | `env('FORM_SHIELD_DNS_CHECK', true)` | Turns the MX lookup on or off. Turn it off in CI. |
| `disposable_domains` / `disposable_domains_file` | small built-in list | Domains that fire `disposable_email`. |
| `exemption` | `App\Models\User`, `email` | The known-sender exemption. |
| `content` | 2 links, a few phrases | Settings for the `NoSpamContent` rule. |
| `rate_limits` | off | Per-IP caps and an allowlist. See [rate limits](rate-limits.md). |
| `middleware` | reject 429, pass expired | What the middleware rejects. |
| `events` | `true` | Whether the package dispatches its events. |
| `route` | disabled | The JSON fields endpoint. |
| `store` | manual, encrypted, 90 days | The built-in submissions log. See [storing and reviewing](storing-and-reviewing.md#option-2-the-built-in-log). |
| `profiles` | `default` | Per-form overrides. See [profiles](profiles.md). |

## Environment variables

```dotenv
FORM_SHIELD_DNS_CHECK=false          # in CI and local dev
FORM_SHIELD_IP_ALLOWLIST=10.0.0.0/8,203.0.113.4
FORM_SHIELD_STORE=true               # log every inspection to form_shield_submissions
```

## Artisan commands

| Command | What it does |
|---|---|
| `form-shield:install [--table=] [--log] [--migrate] [--force]` | Publishes the config and sets up verdict storage. |
| `form-shield:columns {table} [--migrate]` | Writes a migration adding the spam columns to an existing table. |
| `form-shield:report [model] [--days=30] [--profile=default]` | Precision per signal against reviewed rows. With no model, it reports on the built-in log. |

## Publishing other resources

```bash
php artisan vendor:publish --tag=form-shield-views    # the Blade component
php artisan vendor:publish --tag=form-shield-lang     # messages
php artisan vendor:publish --tag=form-shield-assets   # JS files to public/vendor/form-shield
php artisan vendor:publish --tag=form-shield-migrations  # the built-in log table
```
