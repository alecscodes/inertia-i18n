<?php

use AlecsCodes\InertiaI18n\InertiaI18nServiceProvider;
use Inertia\Inertia;
use Inertia\Response;

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

        expect(inertiaProps(Inertia::render('Test'))['locale'])->toBe('it');
    });

    it('resolves the locale prop when the response renders, not at boot', function () {
        // Providers have booted; locale middleware runs afterwards.
        app()->setLocale('ro');

        expect(inertiaProps(Inertia::render('Test'))['locale'])->toBe('ro');
    });

    it('shares a null translations prop when shared config is empty', function () {
        $props = inertiaProps(Inertia::render('Test'));

        expect($props)->toHaveKey('translations');
        expect($props['translations'])->toBeNull();
    });

    it('shares translations when shared config lists groups', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/common.php", "<?php\nreturn ['site_name' => 'My App'];");
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;

        config(['inertia-i18n.shared' => ['common']]);

        expect(inertiaProps(Inertia::render('Test'))['translations'])->toBe([
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

        $this->langBase = withLangFiles(['en/nav.php' => ['home' => 'Home']]);
        $props = inertiaProps(Inertia::render('Test')->withTranslations('nav'));

        expect($props)->not->toHaveKey('translations');
        expect($props['current_locale'])->toBe('en');
        expect($props['i18n'])->toBe(['nav.home' => 'Home']);
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

    it('resolves locale and shared groups lazily, after the response is built', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'it/nav.php' => ['home' => 'Casa'],
            'it/common.php' => ['site' => 'Sito'],
        ]);
        app()->setLocale('en');

        $response = Inertia::render('Test')->withTranslations('nav');

        config(['inertia-i18n.shared' => ['common']]);
        app()->setLocale('it');

        expect(inertiaProps($response)['translations'])->toEqual([
            'common.site' => 'Sito',
            'nav.home' => 'Casa',
        ]);
    });

    it('loads only shared groups when called without arguments', function () {
        $this->langBase = withLangFiles(['en/common.php' => ['site' => 'Site']]);
        config(['inertia-i18n.shared' => ['common']]);

        $props = inertiaProps(Inertia::render('Test', ['keep' => 'yes'])->withTranslations());

        expect($props['keep'])->toBe('yes');
        expect($props['translations'])->toBe(['common.site' => 'Site']);
    });

    it('uses the groups of the last call when called repeatedly', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['x' => 'Nav'],
            'en/other.php' => ['x' => 'Other'],
        ]);

        $props = inertiaProps(Inertia::render('Test')->withTranslations('nav')->withTranslations('other'));

        expect($props['translations'])->toBe(['other.x' => 'Other']);
    });

    it('fills missing keys from the fallback locale', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home', 'about' => 'About'],
            'it/nav.php' => ['home' => 'Casa'],
        ]);
        app('translator')->setFallback('en');
        app()->setLocale('it');

        expect(inertiaProps(Inertia::render('Test')->withTranslations('nav'))['translations'])->toEqual([
            'nav.home' => 'Casa',
            'nav.about' => 'About',
        ]);
    });

    it('sends the catalog as a JSON object, even when empty or keyed by integers', function () {
        $this->langBase = withLangFiles(['en.json' => '{"0": "zero", "1": "one"}']);

        $empty = inertiaProps(Inertia::render('Test')->withTranslations('missing'), assoc: false);
        $numeric = inertiaProps(Inertia::render('Test')->withTranslations('@json'), assoc: false);

        expect($empty->translations)->toEqual(new stdClass);
        expect($numeric->translations)->toEqual((object) ['0' => 'zero', '1' => 'one']);
    });

    it('does not load any catalog for partial reloads that exclude it', function () {
        $marker = sys_get_temp_dir().'/inertia-i18n-loaded-'.uniqid();
        $this->langBase = withLangFiles([
            'en/nav.php' => "<?php file_put_contents('{$marker}', 'x'); return ['home' => 'Home'];",
        ]);
        config(['inertia-i18n.shared' => ['nav']]);
        $partial = ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Test', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'locale'];

        expect(inertiaProps(Inertia::render('Test'), $partial))->toBe(['locale' => 'en']);
        expect(inertiaProps(Inertia::render('Test')->withTranslations('nav'), $partial))->toBe(['locale' => 'en']);
        expect(file_exists($marker))->toBeFalse();

        // Sanity check: the marker is written when the catalog is requested.
        inertiaProps(Inertia::render('Test'));
        expect(file_exists($marker))->toBeTrue();
        unlink($marker);
    });

});

// ---------------------------------------------------------------------------
// Publishing
// ---------------------------------------------------------------------------

it('publishes the JavaScript helper and the config file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'inertia-i18n-js', '--force' => true])->assertExitCode(0);
    $this->artisan('vendor:publish', ['--tag' => 'inertia-i18n-config', '--force' => true])->assertExitCode(0);

    expect(resource_path('js/vendor/inertia-i18n/useTranslation.ts'))->toBeFile();
    expect(config_path('inertia-i18n.php'))->toBeFile();
});
