# Tuning with the report

```bash
php artisan form-shield:report "App\Models\Inquiry"
php artisan form-shield:report "App\Models\Inquiry" --days=90 --profile=contact
php artisan form-shield:report          # the built-in form_shield_submissions log
```

## Reading it

| Column | Meaning |
|---|---|
| Fired | Rows in the window where the signal fired. |
| Reviewed | Of those, how many a person labelled. |
| Reviewed as ham | Of those, how many the person said were legitimate. |
| Precision | `(Reviewed − Reviewed as ham) / Reviewed`: when this signal fires, how often it's right. |

Hard signals show `hard` in the Weight column, and only appear when they decided a verdict.

Below the table:

- **Quarantined:** rows currently marked spam.
- **False positives:** reviewed ham that the detector had flagged.
- **Missed spam:** reviewed spam that the detector had passed.

Only reviewed rows count as ground truth. Measuring the detector against its own unreviewed output would just confirm what it already believes.

## What to do with it

- **A soft signal at ~100% over a meaningful sample** (say 50+ reviewed): raise its weight, or make it a hard signal.
- **One below ~90%:** lower its weight, restrict it to the profiles where it works, or remove it.
- **Many misses:** look at their `spam_signals`. If they share a pattern, that's your next custom signal.
- **False positives clustering on one signal:** that signal has an innocent explanation you hadn't considered. Find it before changing numbers.

Change one thing at a time, and note the date in the config file. You'll want to compare windows before and after.

## Getting enough labels

The report is only as good as your labels:

1. Review the quarantine queue regularly. This is where false positives hide.
2. Spot-check clean rows too. This is where misses hide.
3. Leave unclear cases unreviewed. A wrong label is worse than none.
