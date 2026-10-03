# How it works

## The pipeline

Every inspection runs the same steps, in this order:

1. **Rate caps.** If the sender's IP is over a cap, the verdict is `rate_limited` and nothing else runs. See [rate limits](rate-limits.md).
2. **Hard signals.** Each one decides the verdict on its own:
   - `honeypot`: the hidden field was filled. No person fills a field they can't see.
   - `no_token`: the encrypted start timestamp is missing or forged.
   - **expired**: the form was open longer than `max_age_seconds`. This isn't spam; see below.
   - `too_fast`: submitted less than `min_seconds` after the form rendered.
   - any [custom hard signals](custom-signals.md).
3. **Known-sender exemption.** If the email belongs to an existing user, soft scoring is skipped.
4. **Soft signals.** Each one that fires adds its weight to the score. Deferred signals (the MX lookup) run last, and only while the score is still under the threshold.
5. **Threshold.** If the score reaches `threshold` (default 4), the verdict is spam with reason `score`.

## Why the exemption comes after the hard signals

Customer email addresses are easy to scrape. If the exemption ran first, any bot with one customer's address would skip every check at once. A filled honeypot or a forged token is unambiguous no matter whose email is attached.

## Why no single soft signal can quarantine

Every soft signal has innocent explanations. Some people browse with JavaScript blocked. Some send the same message twice. Some write in another language. Each default weight is below the threshold, so it takes at least two independent signals to quarantine someone. Keep it that way when you tune.

## Why quarantine instead of reject

When a submission is flagged:

- the visitor sees the normal success page,
- you store the row anyway, with the verdict,
- you skip the side effects: emails, CRM pushes, Slack pings.

Three things follow:

1. **Bots learn nothing.** A rejection tells the bot author which check to work around. A success page doesn't.
2. **False positives are recoverable.** The message is sitting in your database. Mark it as ham and handle it.
3. **You get data.** Flagged and clean rows both carry their signal map, which is what the report needs to measure precision.

Content rules ([`NoSpamContent`](signals.md#content-rules)) are the exception. Too many links or a blocked phrase is something a real person can fix, so those return a visible validation error.

## Why "expired" isn't spam

Forms sit open in background tabs for hours, and embedded booking forms can sit open for days. A form past `max_age_seconds` is far more likely to be a real person than a bot, so it gets its own verdict state (`$verdict->isExpired`). Ask the visitor to resubmit; don't drop the message.

## What the token protects

The timestamp field holds `encrypt(time())`, made with your app key. It's encrypted rather than plain because a plain hidden timestamp is trivial to forge, and then the timing checks would protect nothing. The JS marker field stays empty until the shield script copies the token into it on the visitor's first focus, input or click. Arming on interaction, not on page load, means a headless client has to simulate a person to fill it.

## Reading a verdict

| Property | Type | Meaning |
|---|---|---|
| `status` | `Enums\VerdictStatus` | `Clean`, `Spam`, `Expired` or `RateLimited` |
| `isSpam`, `isExpired`, `isRateLimited` | `bool` | shortcuts for the status |
| `passes()` | `bool` | clean: process as usual |
| `reason` | `?string` | why it was decided: a `Enums\Reason` value (`honeypot`, `no_token`, `too_fast`, `score`, `expired`, `rate_limited`), or the name of a custom hard signal |
| `knownReason()` | `?Enums\Reason` | the built-in reason as an enum, or null |
| `hasReason(Reason::Honeypot)` | `bool` | compare without string literals |
| `score`, `signals` | `int`, `array<string, int>` | the soft signals that fired, and their total |
| `profile` | `string` | which profile judged it |
| `retryAfter` | `?int` | seconds, when rate limited |

`reason` stays a string because custom hard signals add their own names, and that's also what's stored in `spam_reason`.

## Two facts per row

`HasSpamVerdict` keeps apart what the detector thought and what is currently true:

| Column | Meaning | Written by |
|---|---|---|
| `spam_reason`, `spam_score`, `spam_signals` | the detector's verdict | the detector, once |
| `is_spam` | the effective verdict | the detector, then a reviewer |
| `spam_source`, `reviewed_at`, `reviewed_by` | who decided `is_spam` | a reviewer |

With a single boolean you couldn't tell a correct prediction from a corrected one, and the labels would be useless for tuning.
