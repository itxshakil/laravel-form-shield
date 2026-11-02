# Profiles

A profile is a named set of overrides for one kind of form. Pass its name to `inspect()`, to the middleware, or to the Livewire helper:

```php
FormShield::inspect($request, 'booking');
Route::post('/book', ...)->middleware('form-shield:booking');
$this->inspectFormShield('booking');
```

Define profiles in `config/form-shield.php`:

```php
'profiles' => [
    'default' => [],

    // An embedded booking widget: free text lives in `remarks`, the form
    // can sit open for days, and the visitor is often on a locked-down
    // corporate browser.
    'booking' => [
        'inputs' => ['message' => ['remarks']],
        'max_age_seconds' => 259200,
        'weights' => ['no_js' => 1],
    ],

    // A newsletter box: no message field, so judge the email hard.
    'newsletter' => [
        'threshold' => 3,
        'signals' => ['no_js', 'disposable_email', 'no_mx', 'duplicate'],
        'rate_limits' => ['per_ip_per_day' => 5],
    ],
],
```

Built-in soft signal names are available as an enum, if you prefer them to strings:

```php
use Itxshakil\FormShield\Enums\BuiltInSignal;

'signals' => [BuiltInSignal::NoJavaScript->value, BuiltInSignal::DisposableEmail->value],
```

## What a profile can override

Any top-level key that shapes a verdict: `threshold`, `weights`, `inputs`, `min_seconds`, `max_age_seconds`, `requires_token`, `allowed_fields`, `allowed_scripts`, `disposable_domains`, `email_dns_check`, `duplicate_window_seconds`, `exemption`, `content`, `rate_limits`, plus `signals`.

`weights`, `inputs`, `exemption`, `content` and `rate_limits` are **merged** with the top-level values, so you only list what changes. Everything else is replaced.

`signals` is a list of the soft signals that run for this profile. Leave it out to run all of them. Hard signals always run.

## Forms without a token

Some forms are not rendered by your app, such as a lead form on a customer's own website.
They cannot carry the token fields, so every post would be flagged `no_token`.
Set `requires_token` to `false` on a profile for those forms:

```php
'profiles' => [
    'embedded_lead' => [
        'requires_token' => false,
        'content' => ['signals' => true],
    ],
],
```

The default is `true`, which is the behaviour of v1.
With it off, the token, `expired` and `too_fast` checks are skipped and `no_js` never fires.
The honeypot still applies whenever its field is present in the post, and every other signal runs as usual.
Without the timing checks you lose a strong bot tell, so pair this with a rate limit and the [content signals](signals.md#content-signals).

## Unknown profiles fail loudly

`inspect($request, 'contcat')` throws `InvalidArgumentException`. Silently falling back to the default profile would hide the typo until the wrong rules quarantined someone.

## Rate limits are per profile

`per_ip_per_day` is counted separately for each profile, so a visitor who subscribed to the newsletter still has their full contact-form allowance. `global_per_ip_per_hour` counts across all profiles.
