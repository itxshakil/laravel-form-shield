# Accessibility and privacy

## Accessibility

The honeypot is the one part of the shield a real person could stumble into, and quarantine looks exactly like success, so a person who fills it gets no feedback at all. The component guards against that:

- the wrapper is `aria-hidden="true"`, so screen readers skip it,
- the input is `tabindex="-1"`, so keyboard users never land on it,
- `autocomplete="off"`, so browser autofill leaves it alone,
- it's moved off-screen rather than hidden with `display:none` (which bots know to skip). Its label says "Leave this field empty", in case something exposes it anyway.

Don't name the honeypot after a field browsers autofill (`email`, `phone`, `address`, `website`). The default, `fax_number`, is chosen for that. When you build your own markup (SPA forms), keep all four properties.

No CAPTCHA means no puzzle, no audio challenge, and no time pressure for anyone.

## Privacy

- **No third parties.** Nothing is sent outside your app. There's no external script, cookie or fingerprinting.
- **The timing token** contains only a timestamp, encrypted with your app key.
- **The MX check** sends a DNS query for the email's domain (not the address) to your resolver. Turn it off with `FORM_SHIELD_DNS_CHECK=false` if that matters to you.
- **Rate limits** store a counter keyed by IP in your cache, expiring within a day.
- **Duplicate detection** stores a hash of email + message in your cache for `duplicate_window_seconds`, not the content itself.
- **Stored verdicts** hold signal names and weights, no extra personal data.

Under GDPR-style rules, spam filtering is generally covered by legitimate interest. Mention it in your privacy notice next to the form's own processing. This isn't legal advice.

## CSP

`@formShieldScripts` outputs an inline `<script>` that carries `Vite::cspNonce()` when one is set. If your CSP forbids inline scripts outright, publish the assets (`--tag=form-shield-assets`) and load `public/vendor/form-shield/form-shield.js` as a file instead.

The honeypot wrapper uses an inline `style` attribute by default. If your CSP blocks inline styles, set `honeypot_class` and define the class in your CSS:

```css
.form-shield-trap { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; overflow: hidden; }
```
