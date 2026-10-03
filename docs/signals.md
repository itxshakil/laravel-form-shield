# Signals reference

## Hard signals

A hard signal decides the verdict on its own (`$verdict->reason` holds its name).

| Name | Fires when |
|---|---|
| `honeypot` | The honeypot field is filled with anything, an array included. |
| `no_token` | The timestamp token is missing, isn't a string, or doesn't decrypt with your app key. |
| `too_fast` | Less than `min_seconds` passed between rendering and submitting. |

`expired` (older than `max_age_seconds`) is a verdict state, not a hard signal. See [how it works](how-it-works.md#why-expired-isnt-spam).

## Soft signals

Each one adds its weight when it fires. Default threshold: **4**. The names are also cases of `Itxshakil\FormShield\Enums\BuiltInSignal`.

| Name | Default weight | Fires when | Innocent explanation |
|---|---|---|---|
| `no_js` | 2 | The JS marker doesn't match the token: the shield script never ran, or the visitor never focused or typed. | Scripts blocked; strict privacy extensions. |
| `duplicate` | 3 | The same email + message pair (or the same 20+ character message with no email) was seen within `duplicate_window_seconds`. | Double submit after a slow response. |
| `disposable_email` | 2 | The email domain, or a parent domain, is on the disposable list. | Privacy-minded people. |
| `no_mx` | 2 | The email domain has neither an MX nor an A record. *Deferred.* | Typo in the domain. |
| `foreign_script` | 2 | Under half the message's letters (10+ letters) are in `allowed_scripts`. | A real customer writing in their own language. |
| `no_user_agent` | 2 | The request has no User-Agent header. | Rare: some API clients. |
| `url_in_name` | 3 | The name field contains `http(s)://` or `www.`. | Almost none. |
| `gibberish` | 2 | A word of 8+ ASCII letters in the name or message has a 6+ consonant cluster, or flips case 4+ times (`xKqLmPzRw`). | Some non-English names; product codes. |
| `unexpected_fields` | 2 | The submission has keys outside `allowed_fields`. Off unless that list is set. | Browser extensions that inject fields. |

## Deferred signals

`no_mx` makes a DNS query, and PHP's resolver has no timeout, so it runs after every other soft signal, and only while the score is still under the threshold. Results are cached per domain for a day. Make your own slow signals deferred by implementing `DeferredSignal`.

## The known-sender exemption

When `exemption.enabled` is true and the submitted email matches `exemption.column` on `exemption.model`, soft scoring is skipped. The verdict is clean and its signal map is `['known_sender' => 0]`. The zero weight keeps it out of the report's precision table.

Bind your own `Itxshakil\FormShield\Contracts\SenderExemption` to change the rule, for example to check a CRM, or to exempt logged-in users.

## Content rules

`Itxshakil\FormShield\Rules\NoSpamContent` is a validation rule, not a signal. It fails visibly, so a real person can fix the message:

- more than `content.max_links` links (links are counted, because real customers paste their own website),
- HTML `<a href>` or BBCode `[url]` markup,
- any phrase in `content.phrases`, case-insensitive.

```php
'message' => ['required', new NoSpamContent],
'message' => ['required', NoSpamContent::make()->maxLinks(0)->phrases(['buy now'])],
```

Grow the phrase list from what you actually receive, not from imagination. Every entry is a chance to turn away a paying customer, so use multi-word phrases only.
