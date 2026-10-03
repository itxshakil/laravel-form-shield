# Storing verdicts and reviewing

You have two options. Pick one per form; you can mix them across forms.

| | Columns on your table | Built-in log |
|---|---|---|
| Use when | the form already saves a row (`inquiries`, `leads`, `bookings`) | the form only sends an email, or you want every form's verdicts in one place |
| Setup | `php artisan form-shield:columns inquiries` | `php artisan form-shield:install --log` |
| Model | your model + `HasSpamVerdict` | `Itxshakil\FormShield\Models\ShieldSubmission` |
| Report | `form-shield:report "App\Models\Inquiry"` | `form-shield:report` |

`php artisan form-shield:install` asks which one you want.

## Option 1: columns on your own table

```bash
php artisan form-shield:columns inquiries            # writes the migration
php artisan form-shield:columns inquiries --migrate  # ...and runs it
```

The command refuses a table that already has spam columns, won't write a second migration for the same table, and warns (but still writes) if the table doesn't exist yet. The migration it writes is just:

```php
Schema::create('inquiries', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->text('message');
    $table->spamColumns();
    $table->timestamps();
});

// down()
$table->dropSpamColumns();
```

`spamColumns()` adds:

| Column | Type |
|---|---|
| `is_spam` | boolean, indexed, default false |
| `spam_reason` | string(64), nullable |
| `spam_score` | unsigned small int, default 0 |
| `spam_signals` | json, nullable |
| `spam_source` | string(16), default `detector` |
| `reviewed_at` | timestamp, nullable |
| `reviewed_by` | string, nullable |

### The model

```php
use Itxshakil\FormShield\Concerns\HasSpamVerdict;

class Inquiry extends Model
{
    use HasSpamVerdict;
}
```

The trait adds casts, and:

```php
Inquiry::createWithVerdict($data, $verdict);
// or, in two steps:
Inquiry::make($data)->applyVerdict($verdict)->save();

Inquiry::spam()->get();
Inquiry::notSpam()->get();
Inquiry::unreviewed()->spam()->latest()->paginate();   // the review queue

$inquiry->markAsHam($admin);      // Authenticatable, an id, a string, or null
$inquiry->markAsSpam($admin);

$inquiry->detectorFlagged();      // what the detector said, whatever happened since
$inquiry->isFalsePositive();      // reviewed ham, detector flagged it
$inquiry->isMissedSpam();         // reviewed spam, detector passed it
```

Don't spread `$verdict->toAttributes()` into `create()`. If the model has a `$fillable` list (most do), Laravel silently drops the spam columns, and the row is saved as "not spam". Under `Model::shouldBeStrict()` you get an exception instead. `createWithVerdict()` and `applyVerdict()` force-fill only the verdict columns; your own `$data` still goes through `$fillable`.

`markAsSpam()` and `markAsHam()` never touch `spam_reason`, `spam_score` or `spam_signals`, and they dispatch `SubmissionReviewed`.

## Option 2: the built-in log

```bash
php artisan form-shield:install --log --migrate
# or: php artisan vendor:publish --tag=form-shield-migrations && php artisan migrate
```

This creates `form_shield_submissions`, with columns for: profile, email, payload (encrypted), ip, user agent, an optional polymorphic `subject`, and the spam columns.

```php
$verdict = FormShield::inspect($request, 'contact');
$record = FormShield::record($request, $verdict);

// later, if your app does create something from it:
$record->attachTo($ticket);
// or in one go: FormShield::record($request, $verdict, $ticket);
```

Set `FORM_SHIELD_STORE=true` (`store.auto`) to log every inspection without calling `record()`. `FormShield::recorded($request)` returns the row written for the current request.

What gets stored:

- **The payload**, minus `_token`, `_method`, the shield fields and every key in `store.redact` (passwords and card fields by default). Uploaded files become a `[Illuminate\Http\UploadedFile]` placeholder, and long strings are capped at 10,000 characters.
- **Encrypted at rest** with your app key (`store.encrypt_payload`). Rotating `APP_KEY` makes old payloads unreadable; the verdict columns are unaffected.
- **Not stored:** rate-limited and expired submissions. Logging floods would let a bot fill your table.

`ShieldSubmission` uses `HasSpamVerdict`, so everything below (scopes, `markAsHam()`, `markAsSpam()`, events) works the same.

### Pruning the log

```php
// routes/console.php
Schedule::command('model:prune', ['--model' => [\Itxshakil\FormShield\Models\ShieldSubmission::class]])->daily();
```

Unreviewed rows older than `store.retention_days` (90) are removed. Reviewed rows are kept, because they're the report's ground truth.

To customise the model (extra relations, a different connection), extend `ShieldSubmission` and set `store.model`.

## Store clean submissions too

Store the verdict on **every** row, not only flagged ones. The report needs the signal maps of clean rows to count misses, and to see which signals fire on legitimate traffic.

## Handling the ham decision

`markAsHam()` only records the label. To send the email the detector held back, listen for the event:

```php
Event::listen(function (SubmissionReviewed $event) {
    if ($event->submission instanceof Inquiry && $event->changedVerdict() && ! $event->isSpam) {
        Mail::to('team@your-company.com')->queue(new NewInquiry($event->submission));
    }
});
```

## A minimal review screen

Any admin UI works: Filament, Nova, or a plain Blade table. You need a list of `unreviewed()->spam()` rows, and two buttons calling `markAsHam()` / `markAsSpam()`. Showing `spam_signals` next to each row helps reviewers see why it was flagged.

Review a sample of **clean** rows now and then too. Misses only show up in the report if someone marks them.

## Pruning

Nothing is pruned automatically. With Laravel's `Prunable`:

```php
public function prunable(): Builder
{
    return static::spam()->unreviewed()->where('created_at', '<', now()->subDays(90));
}
```

Keep reviewed rows: they're your ground truth.
