# Custom signals

## A closure

```php
use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Profile;
use Itxshakil\FormShield\Submission;

// In a service provider's boot()
FormShield::extend('mentions_crypto', function (Submission $submission, Profile $profile): bool {
    return str_contains(mb_strtolower($profile->message($submission)), 'crypto');
}, weight: 2);
```

The weight is written to `form-shield.weights.mentions_crypto`. You can also set it in the config file, or per profile.

## A class

```php
use Itxshakil\FormShield\Contracts\Signal;

final class SubjectInAllCaps implements Signal
{
    public function name(): string
    {
        return 'all_caps_subject';
    }

    public function fires(Submission $submission, Profile $profile): bool
    {
        $subject = $submission->string('subject');

        return mb_strlen($subject) > 10 && $subject === mb_strtoupper($subject);
    }
}

FormShield::extend('all_caps_subject', SubjectInAllCaps::class, weight: 1);
```

Classes are resolved from the container, so constructor injection works.

## Hard signals

A hard signal quarantines on its own and runs before the known-sender exemption:

```php
FormShield::extendHard('blocked_ip', fn (Submission $s) => in_array($s->ip(), $blocked, true));
```

or implement `Itxshakil\FormShield\Contracts\HardSignal`.

## Deferred signals

Implement `Itxshakil\FormShield\Contracts\DeferredSignal` for anything slow or networked, such as a reputation API. It runs last, and only while the verdict is still undecided.

## Removing a built-in

```php
FormShield::forget('foreign_script');      // everywhere
```

To drop a signal for one form only, list the signals you want in that profile's `signals` key.

## Reading the submission safely

`Submission::string($key)` returns `''` for anything that isn't a string, such as arrays, numbers or nulls. Use it, or the profile helpers (`$profile->email()`, `->name()`, `->message()`, which follow the `inputs` map), instead of reaching into `all()`. Bots post hostile shapes on purpose.

## Contributing a signal

If a signal is useful beyond your app, open a PR. Include its precision from `form-shield:report` on real traffic and its innocent explanations. See [CONTRIBUTING.md](../CONTRIBUTING.md).
