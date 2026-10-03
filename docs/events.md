# Events

| Event | When | Payload |
|---|---|---|
| `Itxshakil\FormShield\Events\SubmissionInspected` | after every inspection | `verdict`, `submission` |
| `Itxshakil\FormShield\Events\SubmissionQuarantined` | when the verdict is spam | `verdict`, `submission` |
| `Itxshakil\FormShield\Events\SubmissionReviewed` | `markAsSpam()` / `markAsHam()` | `submission` (the model), `isSpam`, `wasSpam`, `reviewedBy`, `changedVerdict()` |

Set `form-shield.events` to `false` to turn them all off.

## Examples

Count verdicts for a dashboard:

```php
Event::listen(fn (SubmissionInspected $e) => Cache::increment('spam:'.($e->verdict->isSpam ? 'flagged' : 'clean')));
```

Send the held-back email when a reviewer rescues a submission:

```php
Event::listen(function (SubmissionReviewed $e) {
    if ($e->changedVerdict() && ! $e->isSpam) {
        Mail::to('team@your-company.com')->queue(new NewInquiry($e->submission));
    }
});
```

`Submission` holds the raw request input. Don't log it wholesale: it can contain personal data.
