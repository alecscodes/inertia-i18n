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
    | Use '*' to load ALL translation files automatically. Useful for small
    | projects where you want all translations available on every page
    | without using ->withTranslations() in controllers.
    |
    | Example: 'shared' => ['*']           — loads all translation files
    | Example: 'shared' => ['common', 'nav'] — loads only specific groups
    |
    | Leave empty to only load translations via ->withTranslations().
    |
    */

    'shared' => [],

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
    ],
];
