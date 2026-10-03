# Testing your app

## Posting a protected form

Without the shield fields, the inspector sees no token and quarantines the submission, quietly, by design. Add the fields:

```php
use Itxshakil\FormShield\Testing\InteractsWithFormShield;

class ContactTest extends TestCase
{
    use InteractsWithFormShield;

    public function test_a_message_is_delivered(): void
    {
        Mail::fake();

        $this->post('/contact', [
            'email' => 'jane@example.com',
            'message' => 'Hello',
            ...$this->formShieldFields(),
        ])->assertRedirect('/thanks');

        Mail::assertQueued(NewInquiry::class);
    }
}
```

`formShieldFields($secondsAgo = 10, $withJs = true)` mints the token in the past, so there's no `sleep()`.

Set `FORM_SHIELD_DNS_CHECK=false` in `phpunit.xml` so tests never make DNS queries.

## Faking the inspector

```php
$shield = FormShield::fake();             // every submission passes
$shield = FormShield::fake()->flagAll();  // every submission is spam
$shield = FormShield::fake(Verdict::expired());
$shield = FormShield::fake()->using(fn (Submission $s, string $profile) => ...);

$shield->assertInspected('contact');
$shield->assertInspected('contact', fn (Submission $s) => $s->string('email') === 'jane@example.com');
$shield->assertInspectedTimes(1);
$shield->assertNothingInspected();
```

## Testing quarantine

```php
public function test_spam_is_stored_but_not_mailed(): void
{
    Mail::fake();

    $this->post('/contact', [...$valid, ...$this->formShieldFields(), 'fax_number' => 'bot'])
        ->assertRedirect('/thanks');   // same response as a real message

    $this->assertTrue(Inquiry::sole()->is_spam);
    Mail::assertNothingQueued();
}
```

## Livewire

```php
$component = Livewire::test(ContactForm::class);

// What the shield script does in the browser on first interaction:
$component->set('formShield._fs_js', $component->get('formShield._fs_started'));

$this->travel(10)->seconds();   // past min_seconds

$component->set('email', 'jane@example.com')->call('save');
```
