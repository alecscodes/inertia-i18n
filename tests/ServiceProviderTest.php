<?php

use AlecsCodes\InertiaI18n\InertiaI18nServiceProvider;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

function inertiaProps(\Inertia\Response $inertiaResponse): array
{
    $request = \Illuminate\Http\Request::create('/', 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $response = $inertiaResponse->toResponse($request);
    $payload = json_decode($response->getContent(), true);

    return is_array($payload) && isset($payload['props']) && is_array($payload['props'])
        ? $payload['props']
        : [];
}

beforeEach(function () {
    // Reset Inertia shared data between tests
    Inertia::flushShared();

    config()->set('inertia-i18n.props.locale', 'locale');
    config()->set('inertia-i18n.props.translations', 'translations');
    config()->set('inertia-i18n.shared', []);

    (new InertiaI18nServiceProvider(app()))->boot();
});

afterEach(function () {
    if (isset($this->langBase) && is_dir($this->langBase)) {
        File::deleteDirectory($this->langBase);
    }
});

// ---------------------------------------------------------------------------
// Service provider registration
// ---------------------------------------------------------------------------

describe('InertiaI18nServiceProvider', function () {

    it('merges the default config', function () {
        expect(config('inertia-i18n'))->toBeArray()
            ->toHaveKey('shared')
            ->toHaveKey('props');
    });

    it('defaults shared to an empty array', function () {
        expect(config('inertia-i18n.shared'))->toBe([]);
    });

    it('defaults props.translations to "translations"', function () {
        expect(config('inertia-i18n.props.translations'))->toBe('translations');
    });

    it('defaults props.locale to "locale"', function () {
        expect(config('inertia-i18n.props.locale'))->toBe('locale');
    });

    it('registers the withTranslations macro on Inertia Response', function () {
        expect(Response::hasMacro('withTranslations'))->toBeTrue();
    });

    it('shares the locale prop on every Inertia response', function () {
        app()->setLocale('it');

        Inertia::flushShared();
        (new InertiaI18nServiceProvider(app()))->boot();

        $shared = Inertia::getShared();

        expect($shared)->toHaveKey('locale');
        expect(value($shared['locale']))->toBe('it');
    });

    it('does not share translations prop when shared config is empty', function () {
        Inertia::flushShared();
        (new InertiaI18nServiceProvider(app()))->boot();

        $shared = Inertia::getShared();
        $transProp = (string) config('inertia-i18n.props.translations', 'translations');

        expect(array_key_exists($transProp, $shared))->toBeTrue();
        expect(value($shared[$transProp]))->toBeNull();
    });

    it('shares translations when shared config lists groups', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/common.php", "<?php\nreturn ['site_name' => 'My App'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => ['common']]);
        (new InertiaI18nServiceProvider(app()))->boot();

        $shared = Inertia::getShared();
        $transProp = (string) config('inertia-i18n.props.translations', 'translations');

        expect(value($shared[$transProp]))->toBe([
            'common.site_name' => 'My App',
        ]);
    });

    it('respects custom prop names from config', function () {
        config([
            'inertia-i18n.props.locale' => 'current_locale',
            'inertia-i18n.props.translations' => 'i18n',
        ]);

        Inertia::flushShared();
        (new InertiaI18nServiceProvider(app()))->boot();

        $shared = Inertia::getShared();

        expect($shared)->toHaveKey('current_locale');
        expect($shared)->toHaveKey('i18n');
    });

});

// ---------------------------------------------------------------------------
// withTranslations() macro
// ---------------------------------------------------------------------------

describe('withTranslations() macro', function () {

    it('returns an Inertia Response instance', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/nav.php", "<?php\nreturn ['home' => 'Home'];");
        app()->useLangPath($base);
        $this->langBase = $base;

        $response = Inertia::render('Test')->withTranslations('nav');

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('attaches requested translation group to the response', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/nav.php", "<?php\nreturn ['home' => 'Home'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => []]);

        $props = inertiaProps(
            Inertia::render('Test')->withTranslations('nav'),
        );

        expect($props['translations'])->toHaveKey('nav.home', 'Home');
    });

    it('merges shared config groups with explicitly requested groups', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/common.php", "<?php\nreturn ['site_name' => 'My App'];");
        file_put_contents("{$base}/en/nav.php", "<?php\nreturn ['home' => 'Home'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => ['common']]);

        $props = inertiaProps(
            Inertia::render('Test')->withTranslations('nav'),
        );

        expect($props['translations'])
            ->toHaveKey('common.site_name', 'My App')
            ->toHaveKey('nav.home', 'Home');
    });

    it('loads multiple explicitly passed groups', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/nav.php", "<?php\nreturn ['home' => 'Home'];");
        file_put_contents("{$base}/en/auth.php", "<?php\nreturn ['login' => 'Login'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => []]);

        $props = inertiaProps(
            Inertia::render('Test')->withTranslations('nav', 'auth'),
        );

        expect($props['translations'])
            ->toHaveKey('nav.home', 'Home')
            ->toHaveKey('auth.login', 'Login');
    });

    it('uses the current app locale when loading translations', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/it", 0755, true);
        file_put_contents("{$base}/it/nav.php", "<?php\nreturn ['home' => 'Casa'];");
        app()->useLangPath($base);
        app()->setLocale('it');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => []]);

        $props = inertiaProps(
            Inertia::render('Test')->withTranslations('nav'),
        );

        expect($props['translations'])->toHaveKey('nav.home', 'Casa');
    });

    it('supports subdirectory groups via dot-notation', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en/settings", 0755, true);
        file_put_contents("{$base}/en/settings/two_factor.php", "<?php\nreturn ['title' => 'Two-Factor'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => []]);

        $props = inertiaProps(
            Inertia::render('Test')->withTranslations('settings.two_factor'),
        );

        expect($props['translations'])->toHaveKey('settings.two_factor.title', 'Two-Factor');
    });

});
