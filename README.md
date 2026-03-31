# 🌍 inertia-i18n

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alecscodes/inertia-i18n.svg?style=flat-square)](https://packagist.org/packages/alecscodes/inertia-i18n)
[![Total Downloads](https://img.shields.io/packagist/dt/alecscodes/inertia-i18n.svg?style=flat-square)](https://packagist.org/packages/alecscodes/inertia-i18n)
[![License](https://img.shields.io/packagist/l/alecscodes/inertia-i18n.svg?style=flat-square)](LICENSE.md)

Minimal i18n bridge for **Laravel + Inertia.js** — share your PHP and JSON translation files with the frontend.

## Requirements

- PHP `^8.2`
- Laravel `^10.0 || ^11.0 || ^12.0 || ^13.0`
- Inertia Laravel `^2.0 || ^3.0`

## Install

```bash
composer require alecscodes/inertia-i18n
```

## Publish

```bash
php artisan vendor:publish --tag=inertia-i18n-js
php artisan vendor:publish --tag=inertia-i18n-config
```

## Configure

In `config/inertia-i18n.php`, set `shared` to only the groups used on every page (e.g. `common`, `nav`) and load everything else per-page with `->withTranslations()`.

```php
'shared' => ['common', 'nav'];
```

You can also use `['*']` in `config/inertia-i18n.php` to load all translation files on every page. This is a convenient quickstart for small apps (no need to touch each controller), but it can become heavy if you have a lot of translations.

```php
'shared' => ['*'],
```

## Frontend

```ts
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';

const page = /* get the Inertia page object from your adapter */
const { t, tc, locale } = useTranslation(page.props);

t('settings.two_factor.title') // Two-factor authentication
```

## Backend

When using `['*']` in the config to load all translations globally, the `->withTranslations()` calls are not required.

```php
return Inertia::render('Dashboard')
    ->withTranslations('dashboard', 'widgets');
```

## Localization

Translation files are loaded from:

- `lang/{locale}/**/*.php`
- `lang/{locale}/**/*.json`  
  (subdirectories are supported)

Language switching can be implemented using any method you prefer
(e.g., route parameters, session, cookies, etc.).

Set the locale before rendering the Inertia response:

```php
app()->setLocale($locale);
```

---

## 📄 License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

---

<p align="center">Made with ♥ for the Laravel community</p>
