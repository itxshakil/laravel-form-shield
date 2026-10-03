# Laravel Form Shield

[![CI](https://github.com/itxshakil/laravel-form-shield/actions/workflows/ci.yml/badge.svg)](https://github.com/itxshakil/laravel-form-shield/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/itxshakil/laravel-form-shield.svg)](https://packagist.org/packages/itxshakil/laravel-form-shield)
[![Total downloads](https://img.shields.io/packagist/dt/itxshakil/laravel-form-shield.svg)](https://packagist.org/packages/itxshakil/laravel-form-shield)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](phpstan.neon.dist)
[![License](https://img.shields.io/packagist/l/itxshakil/laravel-form-shield.svg)](LICENSE.md)

**Spam scoring for Laravel forms, with no CAPTCHA. Suspected spam is quarantined instead of rejected, and the rules are tuned against your own labelled data.**

A honeypot catches the dumbest bots. A CAPTCHA catches more, but it costs you real visitors, a third-party script, and a privacy question. Rejecting suspected spam outright quietly throws away the occasional real lead, and nobody ever finds out.

Form Shield is the layer after the honeypot:

- **It scores, it doesn't guess.** Hard signals (honeypot, forged token, too fast) flag on their own. Soft signals (no JavaScript, duplicate message, disposable or dead email domain, link in the name field, gibberish...) add up to a threshold, and no single soft signal can quarantine anyone.
- **It quarantines.** A flagged submission gets the normal success page, but no email goes out and no side effects run. You keep the row. Bots learn nothing, and a false positive is one click to recover.
- **It learns from your labels.** Every verdict is stored, spam or not. When someone marks a submission as spam or ham, `php artisan form-shield:report` shows each signal's precision, so you tune weights from data instead of hunches.

Example report output:

```
+------------------+--------+-------+----------+-----------------+-----------+
| Signal           | Weight | Fired | Reviewed | Reviewed as ham | Precision |
+------------------+--------+-------+----------+-----------------+-----------+
| no_js            | 2      | 412   | 96       | 3               | 96.9%     |
| duplicate        | 3      | 188   | 41       | 0               | 100.0%    |
| honeypot         | hard   | 1,204 | 87       | 0               | 100.0%    |
| foreign_script   | 2      | 57    | 12       | 4               | 66.7%     |
+------------------+--------+-------+----------+-----------------+-----------+
```

It works with Blade forms, Livewire, Inertia/SPA forms, JSON APIs, and forms embedded in iframes on other sites.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Install

```bash
composer require itxshakil/laravel-form-shield
php artisan form-shield:install
```

The installer publishes the config, then asks where verdicts should be stored:

- **on a table you already have** (`inquiries`, `leads`...): it writes a migration that adds the spam columns, or
- **in Form Shield's own `form_shield_submissions` table**, for forms that don't store their posts anywhere (say, a contact form that only sends an email).

For scripts and CI: `php artisan form-shield:install --table=inquiries --migrate` or `--log --migrate`.

## Quick start

**1. Add the shield to your form, and the script once in your layout.**

```blade
<form method="POST" action="{{ route('contact.store') }}">
    @csrf
    <x-form-shield />

    <input name="name"> <input name="email"> <textarea name="message"></textarea>
    <button>Send</button>
</form>

{{-- once, e.g. at the end of your layout's <body> --}}
@formShieldScripts
```

**2. Store verdicts.** If you picked an existing table, the installer already wrote the migration. You can also run it on its own:

```bash
php artisan form-shield:columns inquiries
php artisan migrate
```

Then add the trait to the model:

```php
use Itxshakil\FormShield\Concerns\HasSpamVerdict;

class Inquiry extends Model
{
    use HasSpamVerdict;
}
```

**3. Inspect, store, and skip the side effects for spam.**

```php
use Itxshakil\FormShield\Facades\FormShield;

public function store(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email'],
        'message' => ['required', 'string', 'max:5000'],
    ]);

    $verdict = FormShield::inspect($request, 'contact');   // after validation

    if ($verdict->isExpired) {
        return back()->withInput()->withErrors(['message' => __('form-shield::messages.expired')]);
    }

    $inquiry = Inquiry::createWithVerdict($data, $verdict);

    if (! $verdict->isSpam) {
        Mail::to('team@your-company.com')->queue(new NewInquiry($inquiry));
    }

    return to_route('contact.thanks');   // the same response either way
}
```

> **Don't** write `Inquiry::create([...$data, ...$verdict->toAttributes()])`. If your model lists its fields in `$fillable`, Laravel silently drops the spam columns and every row is saved as "not spam". `createWithVerdict()` fills `$data` normally and force-fills only the verdict columns. The two-step equivalent is `Inquiry::make($data)->applyVerdict($verdict)->save()`.

**No table of your own?** Use the built-in log instead of the `Inquiry` model:

```php
$verdict = FormShield::inspect($request, 'contact');
FormShield::record($request, $verdict);   // or set FORM_SHIELD_STORE=true to log every inspection

if (! $verdict->isSpam) {
    Mail::to('team@your-company.com')->queue(new ContactMessage($data));
}
```

**Prefer `match`?** Every verdict has a `status` enum:

```php
use Itxshakil\FormShield\Enums\VerdictStatus;

return match ($verdict->status) {
    VerdictStatus::Clean, VerdictStatus::Spam => to_route('contact.thanks'),
    VerdictStatus::Expired => back()->withInput()->withErrors(['message' => __('form-shield::messages.expired')]),
    VerdictStatus::RateLimited => abort(429),
};
```

**4. Label some submissions, then read the report.**

```php
$inquiry->markAsHam(auth()->user());   // a false positive
$inquiry->markAsSpam(auth()->user());  // a miss
```

```bash
php artisan form-shield:report "App\Models\Inquiry" --days=60
php artisan form-shield:report                                # the built-in log
```

## Optional: reject obvious spam content

The scoring above never shows an error, because the point is that bots can't tell what was caught. A few patterns are different: a real person can fix them, so it's kinder to say so. `NoSpamContent` is a normal validation rule for those:

```php
use Itxshakil\FormShield\Rules\NoSpamContent;

'message' => ['required', 'string', new NoSpamContent],
```

It fails with a visible error for more than 2 links, HTML or BBCode link markup, or a configured phrase like "quality backlinks". Leave it out if you'd rather everything went through quarantine. See [signals](docs/signals.md#content-rules).

## Prefer middleware?

```php
Route::post('/contact', ContactController::class)->middleware('form-shield:contact');

// in the controller
$verdict = $request->spamVerdict();
```

The middleware applies the rate caps up front (a `429`) and, if you turn it on, rejects expired forms (a `422`). It never rejects spam, because quarantine is your controller's job. The inspection itself runs on the first `spamVerdict()` call, so call it **after validation**. A visitor who fixes a typo and resubmits shouldn't be scored as a duplicate of their own first attempt. The same applies when you call `FormShield::inspect()` yourself.

## Livewire

```php
use Itxshakil\FormShield\Livewire\WithFormShield;

class ContactForm extends Component
{
    use WithFormShield;

    public string $email = '';
    public string $message = '';

    public function save(): void
    {
        $verdict = $this->inspectFormShield('contact', ['email' => $this->email, 'message' => $this->message]);
        // ...
    }
}
```

```blade
<form wire:submit="save">
    <x-form-shield wire />
    ...
</form>
```

## Inertia, SPAs, and cross-origin forms

Share `FormShield::fields()` as a prop, or enable the JSON endpoint (`form-shield.route.enabled`). Then arm the shield on the form's first interaction:

```js
import { armShield, fieldNamesFrom } from '../../vendor/itxshakil/laravel-form-shield/resources/js/client.js';

const form = useForm({ email: '', message: '', ...props.formShield });
const names = fieldNamesFrom(props.formShield);
```

```vue
<form @focusin="armShield(form, names)" @submit.prevent="form.post('/contact')">
```

## Testing your app

```php
use Itxshakil\FormShield\Testing\InteractsWithFormShield;

class ContactTest extends TestCase
{
    use InteractsWithFormShield;

    public function test_a_visitor_can_send_a_message(): void
    {
        $this->post('/contact', [...$data, ...$this->formShieldFields()])->assertRedirect('/thanks');
    }

    public function test_spam_is_quarantined(): void
    {
        FormShield::fake()->flagAll();

        $this->post('/contact', $data);

        Mail::assertNothingQueued();
    }
}
```

## Documentation

- [How it works](docs/how-it-works.md): hard vs soft signals, the exemption, and why quarantine
- [Configuration](docs/configuration.md)
- [Profiles](docs/profiles.md): different rules per form
- [Signals reference](docs/signals.md)
- [Custom signals](docs/custom-signals.md)
- [Storing verdicts and reviewing](docs/storing-and-reviewing.md)
- [Tuning with the report](docs/tuning.md)
- [Livewire](docs/livewire.md)
- [JSON, SPA, Inertia and iframe forms](docs/json-and-spa.md)
- [Rate limits](docs/rate-limits.md)
- [Events](docs/events.md)
- [Testing your app](docs/testing.md)
- [Accessibility and privacy](docs/accessibility-and-privacy.md)
- [FAQ](docs/faq.md)

## How it compares

| | Honeypot packages | CAPTCHA (reCAPTCHA, hCaptcha, Turnstile) | Form Shield |
|---|---|---|---|
| Friction for real people | none | a challenge, sometimes | none |
| Third-party script / privacy review | no | yes | no |
| Catches bots that skip the honeypot | rarely | usually | often, via scoring |
| What happens on a false positive | lead lost | lead lost | quarantined, recoverable |
| Tells you which rules are wrong | no | no | yes, precision per signal |

Form Shield isn't a CAPTCHA replacement for high-value targets like login or signup under attack. It's for contact, quote, booking, newsletter and lead forms, where the cost of a lost real message is high and the attacker is a generic bot.

## Origin

Form Shield is extracted from the contact and booking forms of a production hosting platform, where it replaced a CAPTCHA proposal. The design decisions are written up in [docs/how-it-works.md](docs/how-it-works.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md). New signals are especially welcome; please include precision numbers from real traffic if you have them.

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Credits

- [Shakil Alam](https://github.com/itxshakil)
- [All contributors](https://github.com/itxshakil/laravel-form-shield/contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
