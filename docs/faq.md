# FAQ

**Will this stop every bot?**
No. Targeted attackers who run a real browser, wait a few seconds and type unique messages will get through, as they get through honeypots and most CAPTCHAs. Form Shield is built for the generic form-spam bots that make up most of the volume, and for learning which rules work against your traffic.

**Should I use it for login or registration?**
Those face credential stuffing and account abuse, which need rate limiting, email verification and possibly a CAPTCHA. Form Shield's quarantine model fits forms whose output a person reads, like contact, quote, booking, support and lead forms.

**Can I use it together with a CAPTCHA?**
Yes. Add the CAPTCHA result as a custom hard signal and keep the scoring and report.

**What about `spatie/laravel-honeypot`?**
It's a solid honeypot plus timing check that rejects the request. Form Shield does that and goes further: scoring, quarantine, stored verdicts and the report. Use one or the other on a given form, not both.

**A real customer was quarantined. What now?**
Mark the row as ham. Then run the report: a false positive usually points at one signal with an innocent explanation you hadn't considered. See [tuning](tuning.md).

**Our customers write in Arabic / Hindi / Russian.**
Add those scripts to `allowed_scripts`, or set it to `null` to turn `foreign_script` off.

**Why does a test get quarantined with no error?**
It posted without the shield fields. Use `InteractsWithFormShield::formShieldFields()`. See [testing](testing.md).

**Does it work with Octane?**
Yes. Nothing keeps per-request state on singletons.

**Does it work without a database?**
Inspection does (it needs the cache only). Storing verdicts and the report need a table: either columns on yours (`form-shield:columns`) or the built-in log (`form-shield:install --log`).

**My contact form only sends an email. Where do quarantined messages go?**
Into the built-in log. Run `php artisan form-shield:install --log --migrate`, then call `FormShield::record($request, $verdict)`, or set `FORM_SHIELD_STORE=true`. Review them with `ShieldSubmission::spam()->unreviewed()`.
