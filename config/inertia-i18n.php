<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Translation Groups
    |--------------------------------------------------------------------------
    |
    | These translation groups are loaded on every Inertia response.
    | Define here the groups that are commonly used across your app.
    |
    | Use '*' to load ALL translation files in lang/{locale}/ automatically.
    | Useful for small projects where you want all translations available on
    | every page without using ->withTranslations() in controllers.
    |
    | Use '@json' to include Laravel's standard lang/{locale}.json file
    | (the catalog used by __('Some text')).
    |
    | Example: 'shared' => ['*']              — loads all translation files
    | Example: 'shared' => ['common', 'nav']  — loads only specific groups
    | Example: 'shared' => ['common', '@json'] — a group plus lang/{locale}.json
    |
    | Leave empty to only load translations via ->withTranslations().
    |
    */

    'shared' => [],

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | When enabled, keys missing from the current locale are filled in from
    | Laravel's fallback locale (config 'app.fallback_locale'), the same way
    | __() behaves on the backend.
    |
    */

    'fallback' => true,

    /*
    |--------------------------------------------------------------------------
    | Prop Names
    |--------------------------------------------------------------------------
    |
    | Customize the Inertia prop names used for translations and locale.
    | If you change them, pass the same names to useTranslation() on the
    | frontend.
    |
    */

    'props' => [
        'translations' => 'translations',
        'locale' => 'locale',
    ],
];
