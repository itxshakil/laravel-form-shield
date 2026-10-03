<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Field Names
    |--------------------------------------------------------------------------
    |
    | The three inputs <x-form-shield /> renders.
    |
    | Name the honeypot after something your forms will never really ask for.
    | Bots fill it anyway, and a developer can't later add a real field with
    | the same name and start quarantining real leads by accident.
    |
    */

    'fields' => [
        'honeypot' => 'fax_number',
        'timestamp' => '_fs_started',
        'js' => '_fs_js',
    ],

    /*
    | CSS class for the honeypot wrapper. When null, an inline style moves it
    | off-screen. Set a class here if your CSP forbids inline style attributes.
    */

    'honeypot_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Timing Window
    |--------------------------------------------------------------------------
    |
    | A submission faster than min_seconds wasn't typed by a person. The upper
    | bound is generous because forms sit open in background tabs (and in
    | third-party iframes) for hours. A submission past it gets an "expired"
    | verdict, which you should answer with "please resubmit", not by
    | dropping it silently.
    |
    */

    'min_seconds' => 3,

    'max_age_seconds' => 43200,

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    |
    | Soft signals add up, and a submission is quarantined once the total
    | reaches the threshold. Keep every weight below the threshold so no single
    | signal can quarantine a visitor on its own, e.g. someone browsing with
    | JavaScript blocked.
    |
    | Run `php artisan form-shield:report` against human-reviewed rows before
    | changing these, and note the date when you do.
    |
    */

    'threshold' => 4,

    'weights' => [
        'no_js' => 2,
        'duplicate' => 3,
        'disposable_email' => 2,
        'no_mx' => 2,
        'foreign_script' => 2,
        'no_user_agent' => 2,
        'url_in_name' => 3,
        'gibberish' => 2,
        'unexpected_fields' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Names
    |--------------------------------------------------------------------------
    |
    | Which request keys hold the email, name and free text. The first
    | non-empty key in each list wins, so one profile can serve forms with
    | different field names.
    |
    */

    'inputs' => [
        'email' => ['email', 'email_address'],
        'name' => ['name', 'full_name', 'first_name'],
        'message' => ['message', 'body', 'comments', 'remarks', 'description'],
    ],

    /*
    | When a list is set, the `unexpected_fields` signal fires for any posted key
    | outside it (shield fields, _token and _method are always allowed). Bots
    | often post fields the form never had. Null turns the signal off.
    */

    'allowed_fields' => null,

    /*
    |--------------------------------------------------------------------------
    | Signal Tuning
    |--------------------------------------------------------------------------
    */

    // Scripts real visitors write in. A message whose letters are mostly in
    // other scripts fires `foreign_script`. Use PCRE script names (Latin,
    // Cyrillic, Arabic, Han, Devanagari...). Null turns the signal off.
    'allowed_scripts' => ['Latin'],

    // How long a repeated email+message pair counts as a duplicate.
    'duplicate_window_seconds' => 3600,

    // Cache store for duplicate tracking and MX results. Null = default store.
    'cache_store' => null,

    // Runs checkdnsrr() on the email domain. It's the only check that touches
    // the network, so it runs last and only while the verdict is still open.
    'email_dns_check' => (bool) env('FORM_SHIELD_DNS_CHECK', true),

    'disposable_domains' => [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'sharklasers.com',
        'temp-mail.org',
        'tempmail.com',
        'throwawaymail.com',
        'trashmail.com',
        'yopmail.com',
    ],

    // Optional newline-separated list of extra disposable domains, e.g. one
    // you keep in sync with a community-maintained list.
    'disposable_domains_file' => null,

    /*
    |--------------------------------------------------------------------------
    | Known Sender Exemption
    |--------------------------------------------------------------------------
    |
    | A submission whose email matches an existing row here skips the soft
    | scoring. It still goes through the hard checks (honeypot, token,
    | timing), because customer email addresses are cheap to scrape.
    |
    */

    'exemption' => [
        'enabled' => true,
        'model' => 'App\\Models\\User',
        'column' => 'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Rules (NoSpamContent validation rule)
    |--------------------------------------------------------------------------
    |
    | Unlike the bot signals, these reject with a visible error, so a real
    | person can reword and resend. Use multi-word phrases only: every entry
    | is a chance to turn away a paying customer.
    |
    */

    'content' => [
        'max_links' => 2,
        'phrases' => [
            'quality backlinks',
            'guest post opportunity',
            'first page of google',
            'rank your website',
            'increase your traffic',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | per_ip_per_day applies per profile. global_per_ip_per_hour counts across
    | every profile. A request over either cap gets a `rate_limited` verdict.
    | The middleware answers it with a 429 when reject_rate_limited is true.
    | Allowlisted IPs and CIDR ranges skip both caps.
    |
    */

    'rate_limits' => [
        'per_ip_per_day' => null,
        'global_per_ip_per_hour' => null,
        'allowlist' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('FORM_SHIELD_IP_ALLOWLIST', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Behaviour
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'reject_rate_limited' => true,
        'reject_expired' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | SubmissionInspected, SubmissionQuarantined and SubmissionReviewed.
    |
    */

    'events' => true,

    /*
    |--------------------------------------------------------------------------
    | Fields Endpoint
    |--------------------------------------------------------------------------
    |
    | A GET endpoint returning freshly minted shield fields as JSON, for SPAs,
    | Inertia pages and forms embedded on other origins. Off by default.
    |
    */

    'route' => [
        'enabled' => false,
        'uri' => 'form-shield/fields',
        'name' => 'form-shield.fields',
        'middleware' => ['throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Submissions Log
    |--------------------------------------------------------------------------
    |
    | For forms that don't store their posts anywhere (a contact form that only
    | sends an email, say). Publish the table with `php artisan form-shield:install`
    | or `vendor:publish --tag=form-shield-migrations`, then call
    | FormShield::record(), or set `auto` so every inspection is logged.
    |
    | Rate-limited and expired submissions are never logged.
    |
    */

    'store' => [
        'auto' => (bool) env('FORM_SHIELD_STORE', false),

        // Must extend Itxshakil\FormShield\Models\ShieldSubmission.
        'model' => Itxshakil\FormShield\Models\ShieldSubmission::class,

        // Encrypt the logged payload with your app key.
        'encrypt_payload' => true,

        // Input keys never written to the log (case-insensitive). The shield
        // fields, _token and _method are always dropped.
        'redact' => [
            'password',
            'password_confirmation',
            'current_password',
            'card_number',
            'cvv',
            'cvc',
            'ssn',
        ],

        // Unreviewed rows older than this are pruned by `model:prune`.
        // Reviewed rows are kept: they're the report's ground truth.
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | Per-form overrides. Any top-level key above that shapes a verdict can be
    | overridden: threshold, weights (merged), inputs (merged), min_seconds,
    | max_age_seconds, allowed_fields, allowed_scripts, exemption (merged),
    | rate_limits (merged) and `signals` (a list of the soft signals to run;
    | omit to run all of them).
    |
    */

    'profiles' => [
        'default' => [],

        // 'newsletter' => [
        //     'threshold' => 3,
        //     'signals' => ['no_js', 'disposable_email', 'no_mx'],
        //     'rate_limits' => ['per_ip_per_day' => 5],
        // ],
    ],

];
