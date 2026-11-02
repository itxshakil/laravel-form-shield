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
| `excessive_links` | 2 | The message holds more than `content.max_links` links. Off unless `content.signals` is true. | Someone pasting their own pages. |
| `link_markup` | 3 | The message holds `<a href`, `[url` or `[link`. Off unless `content.signals` is true. | Someone pasting HTML from their own site. |
| `blocked_phrase` | 3 | The message holds a phrase from `content.phrases`, case-insensitive. Off unless `content.signals` is true. | A phrase that is too general; keep the list to multi-word phrases. |

`no_js` never fires on a profile with `requires_token` off, because such a form has no script to run.

## Deferred signals

`no_mx` makes a DNS query, and PHP's resolver has no timeout, so it runs after every other soft signal, and only while the score is still under the threshold. Results are cached per domain for a day. Make your own slow signals deferred by implementing `DeferredSignal`.

## The known-sender exemption

When `exemption.enabled` is true and the submitted email matches `exemption.column` on `exemption.model`, soft scoring is skipped. The verdict is clean and its signal map is `['known_sender' => 0]`. The zero weight keeps it out of the report's precision table.

By default the lookup runs through the model's global scopes.
If a tenant or soft-delete scope hides the row from the form's request, set `exemption.without_global_scopes` to `true` and the lookup runs with `withoutGlobalScopes()`.
It is `false` by default, which is the behaviour of v1.

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

## Content signals

A form that cannot show a validation error, such as a lead form posted from another site, cannot use the rule above.
Set `content.signals` to `true` and the same three checks run as soft signals instead: `excessive_links`, `link_markup` and `blocked_phrase`.
They are registered like any other soft signal, so a profile's `signals` list and `weights` apply to them.
They are off by default, which is the behaviour of v1.

The rule and the signals share one implementation (`Itxshakil\FormShield\Support\Content`), so they always agree about what counts as a link, as markup or as a blocked phrase.
Reading `content` from a profile merges it with the top-level values, so a profile can turn the signals on without repeating the phrase list.
