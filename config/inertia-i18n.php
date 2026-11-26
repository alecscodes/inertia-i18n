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
    | Use '*' to load ALL grouped translation files and standard locale JSON.
    | Use '@json' to opt in to only the standard lang/{locale}.json catalog.
    | Useful for small
    | projects where you want all translations available on every page
    | without using ->withTranslations() in controllers.
    |
    | Example: 'shared' => ['*']           — loads all translation files
    | Example: 'shared' => ['@json']       — loads lang/{locale}.json
    | Example: 'shared' => ['common', 'nav'] — loads only specific groups
    |
    | Leave empty to only load translations via ->withTranslations().
    |
    */

    'shared' => [],

    // Overlay missing active-locale keys from Laravel's configured fallback.
    'fallback' => true,

    /*
    |--------------------------------------------------------------------------
    | Prop Names
    |--------------------------------------------------------------------------
    |
    | Customize the Inertia prop names used for translations and locale.
    |
    */

    'props' => [
        'translations' => 'translations',
        'locale' => 'locale',
        'translation_locales' => 'translationLocales',
    ],
];
