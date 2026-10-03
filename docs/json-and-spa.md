# JSON, SPA, Inertia and iframe forms

When the form isn't rendered by Blade, the client needs the shield fields from somewhere, and has to arm them on first interaction.

## Getting the fields

**As an Inertia prop:**

```php
return Inertia::render('Contact', ['formShield' => FormShield::fields()]);
```

**From the JSON endpoint**, for SPAs and forms on other origins:

```php
// config/form-shield.php
'route' => [
    'enabled' => true,
    'uri' => 'form-shield/fields',
    'name' => 'form-shield.fields',
    'middleware' => ['throttle:60,1'],   // add CORS handling if it's called cross-origin
],
```

`GET /form-shield/fields` returns:

```json
{
  "names":  { "honeypot": "fax_number", "timestamp": "_fs_started", "js": "_fs_js" },
  "fields": { "fax_number": "", "_fs_started": "eyJpdiI6...", "_fs_js": "" }
}
```

Responses are `Cache-Control: no-store`. Fetch fields when the form is shown, not when the page loads, if the page can sit open for a long time.

## Arming

`resources/js/client.js` is a small ES module:

```js
import { fetchShield, armShield, fieldNamesFrom } from '../../vendor/itxshakil/laravel-form-shield/resources/js/client.js';
```

**Inertia (Vue):**

```vue
<script setup>
const props = defineProps({ formShield: Object });
const form = useForm({ name: '', email: '', message: '', ...props.formShield });
const names = fieldNamesFrom(props.formShield);
</script>

<template>
  <form @focusin.once="armShield(form, names)" @submit.prevent="form.post('/contact')">
    <!-- keep the honeypot in the DOM so bots can find it -->
    <div aria-hidden="true" style="position:absolute;left:-9999px">
      <input :name="names.honeypot" v-model="form[names.honeypot]" tabindex="-1" autocomplete="off">
    </div>
    ...
  </form>
</template>
```

**React (Inertia `useForm`):**

```jsx
const { data, setData, post } = useForm({ email: '', message: '', ...formShield });
const names = fieldNamesFrom(formShield);

<form onFocus={() => setData(armShield({ ...data }, names))} onSubmit={(e) => { e.preventDefault(); post('/contact'); }}>
```

## The server side

Nothing changes: `FormShield::inspect($request, 'contact')` or the `form-shield:contact` middleware.

## Embedded booking or lead widgets

Widgets on other sites usually can't use CSRF, which makes the shield their main protection:

- Give them a profile with a long `max_age_seconds` (they sit open for days).
- Lower `no_js` if the host page strips scripts.
- Answer an `expired` verdict with a "please resubmit" message, since the iframe can refetch fields.
