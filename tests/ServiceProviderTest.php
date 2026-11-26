<?php

use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

beforeEach(function () {
    config()->set('inertia-i18n.props.locale', 'locale');
    config()->set('inertia-i18n.props.translations', 'translations');
    config()->set('inertia-i18n.props.translation_locales', 'translationLocales');
    config()->set('inertia-i18n.shared', []);
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

    it('shares the current locale on every Inertia response', function () {
        app()->setLocale('it');
        expect(inertiaProps(Inertia::render('Test'))['locale'])->toBe('it');
    });

    it('shares null translations when shared config is empty', function () {
        $props = inertiaProps(Inertia::render('Test'));
        expect(array_key_exists('translations', $props))->toBeTrue();
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
        $props = inertiaProps(Inertia::render('Test'));
        expect($props['translations'])->toBe(['common.site_name' => 'My App']);
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

    it('keeps shared groups for a no-argument macro call and last repeated call wins without losing other props', function () {
        $this->langBase = withLangFiles([
            'en/shared.php' => ['x' => 'Shared'],
            'en/nav.php' => ['x' => 'Nav'],
            'en/other.php' => ['x' => 'Other'],
        ]);
        app()->setLocale('en');
        config(['inertia-i18n.shared' => ['shared']]);

        $noArgs = inertiaProps(Inertia::render('Test', ['keep' => 'yes'])->withTranslations());
        expect($noArgs['keep'])->toBe('yes')->and($noArgs['translations'])->toMatchArray([
            'shared.x' => 'Shared',
        ]);

        $repeated = Inertia::render('Test')->withTranslations('nav')->withTranslations('other');
        $props = inertiaProps($repeated);
        expect($props['translations'])->toMatchArray([
            'shared.x' => 'Shared',
            'other.x' => 'Other',
        ])->and($props['translations'])->not->toHaveKey('nav.x');
    });

    it('publishes the configured JavaScript asset and config file through their package tags', function () {
        $this->artisan('vendor:publish', ['--tag' => 'inertia-i18n-js', '--force' => true])->assertExitCode(0);
        $this->artisan('vendor:publish', ['--tag' => 'inertia-i18n-config', '--force' => true])->assertExitCode(0);

        expect(file_exists(resource_path('js/vendor/inertia-i18n/useTranslation.ts')))->toBeTrue();
        expect(file_exists(config_path('inertia-i18n.php')))->toBeTrue();
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

    it('resolves locale after macro construction and reads shared groups lazily', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'it/nav.php' => ['home' => 'Casa'],
            'it/common.php' => ['site' => 'Sito'],
        ]);
        config(['inertia-i18n.shared' => []]);
        app()->setLocale('en');
        $response = Inertia::render('Test')->withTranslations('nav');
        config(['inertia-i18n.shared' => ['common']]);
        app()->setLocale('it');

        $props = inertiaProps($response);
        expect($props['translations'])->toEqual(['common.site' => 'Sito', 'nav.home' => 'Casa']);
    });

    it('does not resolve catalogs for partial reloads that exclude them', function () {
        $counter = sys_get_temp_dir().'/inertia-i18n-counter-'.uniqid();
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/nav.php", "<?php file_put_contents(".var_export($counter, true).", 'loaded', FILE_APPEND); return ['home' => 'Home'];");
        app()->useLangPath($base);
        $this->langBase = $base;
        config(['inertia-i18n.shared' => ['nav']]);
        $headers = [
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Test',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'locale',
        ];
        $shared = inertiaProps(Inertia::render('Test'), $headers);
        expect($shared)->not->toHaveKey('translations')->not->toHaveKey('translationLocales');
        expect(file_exists($counter))->toBeFalse();

        $macro = inertiaProps(Inertia::render('Test')->withTranslations('nav'), $headers);
        expect($macro)->not->toHaveKey('translations')->not->toHaveKey('translationLocales');
        expect(file_exists($counter))->toBeFalse();

        $onlyMetadata = inertiaProps(Inertia::render('Test')->withTranslations('nav'), [
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Test',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'translationLocales',
        ]);
        expect($onlyMetadata)->toHaveKey('translationLocales');
        expect(file_get_contents($counter))->toBe('loaded');
        @unlink($counter);
    });

    it('resolves shared catalogs again when locale changes in the same request binding', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'fr/nav.php' => ['home' => 'Accueil'],
        ]);
        config(['inertia-i18n.shared' => ['nav']]);
        $request = inertiaRequest();
        app()->setLocale('en');
        expect(inertiaProps(Inertia::render('Test'), [], $request)['translations']['nav.home'])->toBe('Home');
        app()->setLocale('fr');
        $props = inertiaProps(Inertia::render('Test'), [], $request);
        expect($props['locale'])->toBe('fr')->and($props['translations']['nav.home'])->toBe('Accueil');
    });

    it('re-evaluates a previously constructed macro response after the locale changes', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'fr/nav.php' => ['home' => 'Accueil'],
        ]);
        config(['inertia-i18n.shared' => []]);
        app()->setLocale('en');
        $response = Inertia::render('Test')->withTranslations('nav');
        $request = inertiaRequest();
        expect(inertiaProps($response, [], $request)['translations']['nav.home'])->toBe('Home');
        app()->setLocale('fr');
        $props = inertiaProps($response, [], $request);
        expect($props['locale'])->toBe('fr')->and($props['translations']['nav.home'])->toBe('Accueil');
    });

    it('invalidates shared request-local catalogs when the configured lang root changes', function () {
        $first = withLangFiles(['en/nav.php' => ['home' => 'First']]);
        $this->langBase = $first;
        config(['inertia-i18n.shared' => ['nav']]);
        $request = inertiaRequest();
        app()->useLangPath($first);
        app()->setLocale('en');
        expect(inertiaProps(Inertia::render('Test'), [], $request)['translations']['nav.home'])->toBe('First');

        $second = withLangFiles(['en/nav.php' => ['home' => 'Second']]);
        app()->useLangPath($second);
        $secondProps = inertiaProps(Inertia::render('Test'), [], $request);
        expect($secondProps['translations']['nav.home'])->toBe('Second');
        File::deleteDirectory($second);
    });

    it('invalidates shared catalogs when fallback configuration changes on the same request', function () {
        $this->langBase = withLangFiles(['en/nav.php' => ['home' => 'Home']]);
        app('translator')->setFallback('en');
        app()->setLocale('fr');
        config(['inertia-i18n.shared' => ['nav'], 'inertia-i18n.fallback' => true]);
        $request = inertiaRequest();
        expect(inertiaProps(Inertia::render('Test'), [], $request)['translations'])->toBe(['nav.home' => 'Home']);

        config(['inertia-i18n.fallback' => false]);
        expect(inertiaProps(Inertia::render('Test'), [], $request)['translations'])->toBe([]);
    });

    it('serializes numeric and dangerous catalog keys as objects for shared and macro props', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/en.json', '{"0":"zero","1":"one"}');
        app()->useLangPath($base);
        app()->setLocale('en');
        $this->langBase = $base;
        config(['inertia-i18n.shared' => ['@json']]);

        $shared = rawInertiaProps(Inertia::render('Test'));
        expect($shared->translations)->toBeInstanceOf(\stdClass::class);
        expect(property_exists($shared->translations, '0'))->toBeTrue();
        expect(property_exists($shared->translations, '1'))->toBeTrue();

        file_put_contents($base.'/en.json', '{"01":"leading","__proto__":"safe","constructor":"ctor","toString":"string"}');
        $sharedDangerous = rawInertiaProps(Inertia::render('Test'));
        expect($sharedDangerous->translations->{'01'})->toBe('leading');
        expect($sharedDangerous->translations->__proto__)->toBe('safe');
        expect($sharedDangerous->translations->constructor)->toBe('ctor');
        expect($sharedDangerous->translations->toString)->toBe('string');

        config(['inertia-i18n.shared' => []]);
        $macro = rawInertiaProps(Inertia::render('Test')->withTranslations('@json'));
        expect($macro->translations)->toBeInstanceOf(\stdClass::class);
        expect($macro->translations->{'01'})->toBe('leading');
        expect($macro->translations->__proto__)->toBe('safe');
    });

    it('preserves numeric keys in fallback provenance as a wire object', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/en.json', '{"0":"fallback","01":"leading fallback"}');
        app()->useLangPath($base);
        app('translator')->setFallback('en');
        app()->setLocale('fr');
        $this->langBase = $base;
        config(['inertia-i18n.shared' => ['@json']]);

        $props = rawInertiaProps(Inertia::render('Test'));
        expect($props->translationLocales)->toBeInstanceOf(\stdClass::class);
        expect(property_exists($props->translationLocales, '0'))->toBeTrue();
        expect($props->translationLocales->{'01'})->toBe('en');
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
