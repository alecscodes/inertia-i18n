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

## Configure (optional)

Set `shared` to only the groups used on every page (e.g. `common`, `nav`) and load everything else per-page with `->withTranslations()`.

You can also use `['*']` to load all translation files on every page. This is a convenient quickstart for small apps (no need to touch each controller), but it can become heavy if you have a lot of translations.

```php
// config/inertia-i18n.php
'shared' => ['*'],
```

## Backend

```php
return Inertia::render('Dashboard')
    ->withTranslations('common', 'nav');
```

## Frontend

```ts
import { useTranslation } from '@/vendor/inertia-i18n/useTranslation';

const page = /* get the Inertia page object from your adapter */
const { t, tc, locale } = useTranslation(page.props);
```

## Translation files

Supports `lang/{locale}/**/*.php` and `lang/{locale}/**/*.json` (subdirectories included).

Example key:

```ts
t('settings.two_factor.title')
```

---

## 📄 License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

---

<p align="center">Made with ♥ for the Laravel community</p>
