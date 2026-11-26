<?php

use AlecsCodes\InertiaI18n\Tests\CustomPropsTestCase;
use Inertia\Inertia;

uses(CustomPropsTestCase::class);

it('uses configured shared and macro prop names for catalogs and provenance', function () {
    $this->langBase = withLangFiles([
        'en/nav.php' => ['home' => 'Home'],
        'en/fallback.php' => ['label' => 'Fallback'],
    ]);
    app('translator')->setFallback('en');
    app()->setLocale('fr');
    config(['inertia-i18n.shared' => ['nav']]);

    $shared = inertiaProps(Inertia::render('Test'));
    expect($shared['current_locale'])->toBe('fr')
        ->and($shared['i18n'])->toBe(['nav.home' => 'Home'])
        ->and($shared['i18nLocales'])->toBe(['nav.home' => 'en']);

    config(['inertia-i18n.shared' => []]);
    $macro = inertiaProps(Inertia::render('Test')->withTranslations('fallback'));
    expect($macro['i18n'])->toBe(['fallback.label' => 'Fallback'])
        ->and($macro['i18nLocales'])->toBe(['fallback.label' => 'en']);
});
