# inertia-i18n

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alecscodes/inertia-i18n.svg?style=flat-square)](https://packagist.org/packages/alecscodes/inertia-i18n)
[![License](https://img.shields.io/packagist/l/alecscodes/inertia-i18n.svg?style=flat-square)](LICENSE.md)

A small Laravel–Inertia bridge that sends selected Laravel-style PHP and JSON catalogs as page props. Its copied TypeScript helper provides adapter-neutral `t()` and `tc()` functions. It is not a replacement for Laravel's full localization API.

## Requirements and compatibility

The package requires PHP `^8.2`, Laravel components `^10.0 || ^11.0 || ^12.0 || ^13.0`, and Inertia Laravel `^2.0 || ^3.0`. Not every combination is supported; CI exercises these conditional families:

| PHP | Laravel | Inertia Laravel | Testbench | Pest |
| --- | --- | --- | --- | --- |
| 8.2 | 10 | 2 | 8 | 2 |
| 8.2 | 11 (11.35+) | 2 or 3 | 9 | 3 |
| 8.2 | 12 | 2 or 3 | 10 | 3 |
| 8.3 | 13 | 2 or 3 | 11 | 3 |
| 8.4 | 13 | 3 | 11 | 4 |

Inertia Laravel 3 requires Laravel 11.35 or newer, so Laravel 10 is paired only with Inertia 2. Laravel 13 requires PHP 8.3 or newer. The package does not add namespaced/package translation catalogs.

## Install and publish

```sh
composer require alecscodes/inertia-i18n
php artisan vendor:publish --tag=inertia-i18n-config
php artisan vendor:publish --tag=inertia-i18n-js
```

Publishing copies the standalone helper into your application (by default under `resources/js/vendor/inertia-i18n`). After upgrading this package, first review and consciously preserve or reconcile any local edits to the copied file, then overwrite it with the upgraded helper:

```sh
php artisan vendor:publish --tag=inertia-i18n-js --force
```

Do not force-publish the config by default. Merge newly introduced config options into your app's published config deliberately.

Configure `config/inertia-i18n.php`. `shared` lists groups sent on every Inertia response; keep it small when catalogs are large. An empty list means no shared catalog and the configured translations prop is `null`. A response can request groups explicitly:

```php
return Inertia::render('Dashboard')
    ->withTranslations('dashboard', 'widgets');
```

`withTranslations()` resolves the configured shared groups together with the page's explicit groups; without arguments it still loads shared groups. The result is empty only when both shared and explicit groups are empty. If called repeatedly on the same response, the last call's explicit groups replace those from earlier calls (shared groups are read at resolution time). Groups are lazy: a partial reload that excludes the catalog props does not load catalog files.

## Catalog files and selectors

Catalogs are read under Laravel's language directory (`lang/`):

```text
lang/
  en.json                   # standard JSON catalog; opt in with @json or *
  en/
    validation.php          # PHP group `validation`
    messages.json           # grouped JSON group `messages`
    admin/
      users.php             # nested group `admin.users`
```

PHP and grouped JSON files may be nested. If both formats exist for the same group, the PHP file supplies that whole group; its keys are not merged with the grouped JSON file. Only string leaves are included when flattening grouped files. A missing file contributes no values. Malformed JSON or JSON that is not an object fails visibly with a file-scoped error; empty objects are valid.

The standard Laravel `lang/{locale}.json` catalog is deliberately opt-in: request the reserved `@json` selector, or use `*`. Selecting a normal group such as `validation` does not implicitly include every root JSON key. Standard JSON keys remain literal, including dots and surrounding whitespace; its nested objects and non-string values are not flattened into keys.

```php
// Share selected groups and standard JSON keys on every page.
'shared' => ['common', '@json'],

// Or all local PHP/grouped-JSON groups plus the standard JSON catalog.
'shared' => ['*'],
```

Wildcard discovery is deterministic and includes groups found only in the fallback locale. Path-like locale and group inputs are rejected. Symlinks escaping the language directory are not followed. These constraints do not provide namespace/package translation support.

## Fallback, precedence, and response props

Fallback is enabled by default and uses Laravel's configured translator fallback locale. Set `fallback` to `false` in the package config to disable it. The active locale's values overlay fallback values, including when both locales define the same key. Within one locale, standard JSON overlays grouped values; PHP wins the entire same-name group rather than merging individual colliding keys from grouped JSON. Among explicitly requested groups, later groups win flattened-key collisions. Active grouped values beat fallback standard-JSON collisions because active-locale values have priority over every fallback format. Empty strings and `'0'` are valid translations.

By default, Inertia receives the configured props `locale`, `translations`, and `translationLocales`. The latter is a sparse map from fallback-resolved keys to the locale that supplied each value; active-locale keys are omitted. Configure the names in `props` if your app needs different ones:

```php
'props' => [
    'locale' => 'locale',
    'translations' => 'translations',
    'translation_locales' => 'translationLocales',
],
```

When no groups are configured as shared, the shared `translations` and `translationLocales` props are `null` (not omitted). Calling `withTranslations()` explicitly resolves a catalog and its source-locale map; if no keys resolve, those are empty maps. Otherwise the locale map contains only fallback-sourced keys.

The same prop names must be configured in the frontend helper. For example, when PHP uses these names:

```php
'props' => [
    'locale' => 'current_locale',
    'translations' => 'i18n',
    'translation_locales' => 'i18nLocales',
],
```

pass the matching names to the helper:

```ts
const translations = useTranslation(() => page.props, {
  props: {
    locale: 'current_locale',
    translations: 'i18n',
    translation_locales: 'i18nLocales',
  },
});
```

## Frontend helper

The helper has no runtime framework dependency. It accepts either a props object or a getter that reads the current adapter page props. Prefer the getter form when page props can be replaced during navigation. Call each adapter's Inertia page API from the normal component render/setup context:

```ts
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';
import { usePage } from '@inertiajs/react';

function DashboardTitle({ user, unreadCount }) {
  const page = usePage(); // Call during render; Inertia updates page props on navigation.
  const { t, tc } = useTranslation(() => page.props);
  return <><h1>{t('dashboard.title', { name: user.name })}</h1>
    <span>{tc('notifications.unread', unreadCount)}</span></>;
}
```

Vue: use `usePage()` in setup and read through a computed value:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';
const page = usePage();
const translations = useTranslation(() => page.props);
const title = computed(() => translations.t('dashboard.title'));
</script>
<template><h1>{{ title }}</h1></template>
```

Svelte 5: Inertia's Svelte adapter exposes its reactive `page` object; `$derived` tracks a helper read:

```svelte
<script lang="ts">
  import { page } from '@inertiajs/svelte';
  import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';
  const translations = useTranslation(() => page.props);
  let title = $derived(translations.t('dashboard.title'));
</script>
<h1>{title}</h1>
```

The returned `locale` and `translations` are getters. Destructuring a primitive such as `locale` gives a snapshot, not a reactive binding. `t(key, params?)` returns the exact key when it is missing. It accepts string/number interpolation parameters. `tc(key, count, params?)` selects a Laravel-style pipe form or explicit interval and uses fallback source-locale metadata for plural selection. A caller-supplied `count` interpolation parameter continues to override the injected parameter; selection itself uses the numeric `count` argument. Non-finite counts throw `RangeError`.

`tc()` accepts pipe-separated forms and Laravel-style exact/range conditions. For example, `{0}none|{1}one|[2,*]many` uses exact counts and an inclusive lower-bounded range; `[*,0]nonpositive|[1,*]positive` uses an open lower bound. Exact/range conditions compare the signed numeric count. Selected conditional text is trimmed. Unmatched recognized conditions are stripped before ordinary positional fallback; positional text otherwise preserves surrounding whitespace.

For positional rules, the package currently uses the absolute count, so negative five selects as five. This matches Laravel 13's current positional normalization, but Laravel 12's current implementation does not take absolute value; negative positional parity therefore differs by framework version. Conditional whitespace handling is intentionally modernized and may differ from older Laravel parsing. Positional selection uses the package's tested locale-index families rather than `Intl.PluralRules` categories; unknown locale codes use the default first-form behavior.

Interpolation recognizes `:name`, `:Name`, and `:NAME` variants for a parameter named `name`. Casing uses JavaScript Unicode-aware casing and first-code-point handling; it is not guaranteed to match PHP casing for every locale or Unicode edge case. Replacement is single-pass and inserted values are literal (including `$` and backslashes); this helper is not a general Laravel message formatter.

## Locale switching and partial reloads

Set Laravel's locale before constructing/rendering the Inertia response. The package resolves it when the response props are evaluated. For a language switch, use a full visit/reload so merged props cannot retain stale catalog entries. If making a partial reload that requests a changed catalog, request the translations, `translationLocales`, and locale props together. If a partial reload excludes catalog props, no catalog files are read.

```php
app()->setLocale($locale);
return Inertia::render('Home')->withTranslations('home');
```

## Local verification

```sh
composer validate --strict
composer install
composer test
npm ci
npm run typecheck
npm test
```

The CI workflow resolves explicit compatible PHP/Laravel/Inertia/Testbench/Pest families, runs PHP syntax checks and tests, and runs the frontend checks on Node 24. A passing matrix row is evidence only for that resolved combination.

## License

MIT. See [LICENSE.md](LICENSE.md).
