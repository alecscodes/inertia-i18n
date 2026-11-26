# 🌍 inertia-i18n

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alecscodes/inertia-i18n.svg?style=flat-square)](https://packagist.org/packages/alecscodes/inertia-i18n)
[![Total Downloads](https://img.shields.io/packagist/dt/alecscodes/inertia-i18n.svg?style=flat-square)](https://packagist.org/packages/alecscodes/inertia-i18n)
[![License](https://img.shields.io/packagist/l/alecscodes/inertia-i18n.svg?style=flat-square)](LICENSE.md)

Minimal i18n bridge for **Laravel + Inertia.js**: share your Laravel translation files with the frontend and use them through `t()` and `tc()`, which behave like Laravel's `__()` and `trans_choice()`.

## Requirements

- PHP `^8.2`
- Laravel `^12.0 || ^13.0`
- Inertia Laravel `^2.0 || ^3.0`

CI tests Laravel 12 and 13 with Inertia 2 and 3 on PHP 8.2–8.5. Laravel 10 and 11 are no longer supported.

## Install

```bash
composer require alecscodes/inertia-i18n
php artisan vendor:publish --tag=inertia-i18n-js
php artisan vendor:publish --tag=inertia-i18n-config
```

The JS tag copies a single dependency-free TypeScript file to `resources/js/vendor/inertia-i18n/useTranslation.ts`. After upgrading the package, re-publish it with `--force` (this overwrites local edits).

## Configure

In `config/inertia-i18n.php`, set `shared` to the groups used on every page and load everything else per page with `->withTranslations()`:

```php
'shared' => ['common', 'nav'],
```

| Value | Loads |
| --- | --- |
| `'nav'` | `lang/{locale}/nav.php` (or `nav.json`) as `nav.*` keys |
| `'settings.two_factor'` | `lang/{locale}/settings/two_factor.php` as `settings.two_factor.*` keys |
| `'*'` | every file in `lang/{locale}/`, including subdirectories |
| `'@json'` | Laravel's `lang/{locale}.json` (the `__('Some text')` catalog), keys unchanged |

`['*']` is a convenient quick start for small apps, but it sends every translation on every page.

Other options:

- `fallback` (default `true`): keys missing from the current locale are filled in from `app.fallback_locale`, like `__()` does.
- `props`: the Inertia prop names (default `locale` and `translations`). If you change them, pass the same names to `useTranslation()` (see below).

## Backend

```php
return Inertia::render('Dashboard')
    ->withTranslations('dashboard', 'widgets');
```

`withTranslations()` sends the shared groups plus the given ones. The locale and catalog are resolved when the response is rendered, so setting the locale in a middleware works as expected. Partial reloads that don't request the `translations` prop don't read any translation files.

Set the locale however you like (route parameter, session, cookie…) before the response is rendered:

```php
app()->setLocale($locale);
```

## Frontend

Pass `useTranslation()` the page props, or better, a function that returns them. With a function, every call reads the current props, so components that survive navigation (persistent layouts, Vue/Svelte setup code) stay up to date after a locale change.

```tsx
// React
import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';

export default function Dashboard({ unread }: { unread: number }) {
    const page = usePage();
    const { t, tc } = useTranslation(() => page.props);

    return (
        <>
            <h1>{t('dashboard.title', { name: 'Jo' })}</h1>
            <p>{tc('dashboard.unread', unread)}</p>
        </>
    );
}
```

```vue
<!-- Vue -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';

const page = usePage();
const { t, tc } = useTranslation(() => page.props);
</script>

<template>
    <h1>{{ t('dashboard.title', { name: 'Jo' }) }}</h1>
</template>
```

The API:

- `t(key, params?)` returns the translation, or `key` itself when it is missing.
- `tc(key, count, params?)` picks a plural form; `:count` is replaced automatically.
- `locale` and `translations` hold the current values.

Placeholders work like Laravel's: `:name`, `:Name` (first letter capitalised) and `:NAME` (upper-cased).

Pluralization follows Laravel's rules:

```php
// lang/en/items.php
'count' => '{0} No items|{1} One item|[2,*] :count items',
// lang/ro/time.php
'minutes' => 'un minut|:count minute|:count de minute',
```

```ts
tc('items.count', 0)   // "No items"
tc('items.count', 5)   // "5 items"
tc('time.minutes', 20) // "20 de minute" (Romanian has three plural forms)
```

Explicit conditions (`{n}`, `[min,max]`, `[n,*]`, `[*,n]`) are checked first. Otherwise the form is chosen by the language's plural rule, the same table as Laravel's `MessageSelector`, so the frontend and `trans_choice()` agree. Locales are matched by language (`pt_BR`, `pt-BR` and `pt` all use the `pt` rule). Unknown languages use the first form.

If you customised the prop names in the config:

```ts
useTranslation(() => page.props, { props: { locale: 'lang', translations: 'i18n' } });
```

**Limitation:** a plural string that comes from the fallback locale is still pluralized with the *current* locale's rule.

## Development

```bash
composer install && composer test
npm ci && npm run typecheck && npm test
```

## 📄 License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

---

<p align="center">Made with ♥ for the Laravel community</p>
