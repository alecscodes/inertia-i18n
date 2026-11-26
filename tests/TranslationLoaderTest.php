<?php

use AlecsCodes\InertiaI18n\TranslationLoader;
use Illuminate\Translation\MessageSelector;

// ---------------------------------------------------------------------------
// load() — basic group loading
// ---------------------------------------------------------------------------

describe('load()', function () {

    it('loads a single flat group', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['dashboard' => 'Dashboard', 'profile' => 'Profile'],
        ]);

        expect(TranslationLoader::load('en', 'nav'))->toBe([
            'nav.dashboard' => 'Dashboard',
            'nav.profile' => 'Profile',
        ]);
    });

    it("loads JSON files when '*' is passed", function () {
        $this->langBase = withLangFiles([
            'en/common.json' => ['site_name' => 'My App'],
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('en', '*'))
            ->toHaveKey('common.site_name', 'My App')
            ->toHaveKey('nav.home', 'Home');
    });

    it('flattens nested arrays to dot-notation keys', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => [
                'links' => [
                    'dashboard' => 'Dashboard',
                    'profile' => 'Profile',
                ],
            ],
        ]);

        expect(TranslationLoader::load('en', 'nav'))->toBe([
            'nav.links.dashboard' => 'Dashboard',
            'nav.links.profile' => 'Profile',
        ]);
    });

    it('merges multiple groups', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en/auth.php' => ['login' => 'Login'],
        ]);

        expect(TranslationLoader::load('en', 'nav', 'auth'))->toBe([
            'nav.home' => 'Home',
            'auth.login' => 'Login',
        ]);
    });

    it('deduplicates repeated group names', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('en', 'nav', 'nav'))->toBe([
            'nav.home' => 'Home',
        ]);
    });

    it('returns empty array for a missing group', function () {
        $this->langBase = withLangFiles([]);

        expect(TranslationLoader::load('en', 'missing'))->toBe([]);
    });

    it('returns empty array for a missing locale directory', function () {
        $this->langBase = withLangFiles([]);

        expect(TranslationLoader::load('xx', 'nav'))->toBe([]);
    });

    it('silently skips non-string leaf values', function () {
        $this->langBase = withLangFiles([
            'en/mixed.php' => [
                'label' => 'Hello',
                'count' => 42,
                'flag' => true,
                'empty' => null,
                'nested' => ['key' => 'Nested'],
            ],
        ]);

        expect(TranslationLoader::load('en', 'mixed'))->toBe([
            'mixed.label' => 'Hello',
            'mixed.nested.key' => 'Nested',
        ]);
    });

    it('returns empty array when group file returns a non-array value', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/bad.php", "<?php\nreturn 'not an array';");
        app()->useLangPath($base);
        $this->langBase = $base;

        expect(TranslationLoader::load('en', 'bad'))->toBe([]);
    });

    it('returns empty array when no groups are passed', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('en'))->toBe([]);
    });

    it('loads a single JSON group', function () {
        $this->langBase = withLangFiles([
            'en/common.json' => ['site_name' => 'My App'],
        ]);

        expect(TranslationLoader::load('en', 'common'))->toBe([
            'common.site_name' => 'My App',
        ]);
    });

    it('loads a JSON subdirectory group with dot-notation', function () {
        $this->langBase = withLangFiles([
            'en/settings/two_factor.json' => ['title' => 'Two-Factor'],
        ]);

        expect(TranslationLoader::load('en', 'settings.two_factor'))->toBe([
            'settings.two_factor.title' => 'Two-Factor',
        ]);
    });

});

// ---------------------------------------------------------------------------
// load() — subdirectory (dot-notation) groups
// ---------------------------------------------------------------------------

describe('load() subdirectory groups', function () {

    it('loads a subdirectory group with dot-notation', function () {
        $this->langBase = withLangFiles([
            'en/settings/two_factor.php' => [
                'title' => 'Two-Factor Authentication',
                'enable' => 'Enable 2FA',
            ],
        ]);

        expect(TranslationLoader::load('en', 'settings.two_factor'))->toBe([
            'settings.two_factor.title' => 'Two-Factor Authentication',
            'settings.two_factor.enable' => 'Enable 2FA',
        ]);
    });

    it('flattens nested keys within a subdirectory group', function () {
        $this->langBase = withLangFiles([
            'en/settings/two_factor.php' => [
                'messages' => [
                    'enabled' => '2FA enabled',
                    'disabled' => '2FA disabled',
                ],
            ],
        ]);

        expect(TranslationLoader::load('en', 'settings.two_factor'))->toBe([
            'settings.two_factor.messages.enabled' => '2FA enabled',
            'settings.two_factor.messages.disabled' => '2FA disabled',
        ]);
    });

    it('loads deeply nested subdirectory groups', function () {
        $this->langBase = withLangFiles([
            'en/admin/settings/security.php' => ['title' => 'Security'],
        ]);

        expect(TranslationLoader::load('en', 'admin.settings.security'))->toBe([
            'admin.settings.security.title' => 'Security',
        ]);
    });

    it('returns empty array for a missing subdirectory group', function () {
        $this->langBase = withLangFiles([]);

        expect(TranslationLoader::load('en', 'settings.missing'))->toBe([]);
    });

    it('can mix flat and subdirectory groups in a single load call', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en/settings/two_factor.php' => ['title' => 'Two-Factor'],
        ]);

        expect(TranslationLoader::load('en', 'nav', 'settings.two_factor'))->toBe([
            'nav.home' => 'Home',
            'settings.two_factor.title' => 'Two-Factor',
        ]);
    });

});

// ---------------------------------------------------------------------------
// load() — wildcard '*'
// ---------------------------------------------------------------------------

describe("load() wildcard '*'", function () {

    it("loads all flat files when '*' is passed", function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en/auth.php' => ['login' => 'Login'],
            'en/common.json' => ['site_name' => 'My App'],
        ]);

        expect(TranslationLoader::load('en', '*'))
            ->toHaveKey('nav.home', 'Home')
            ->toHaveKey('auth.login', 'Login');
    });

    it("loads subdirectory files when '*' is passed", function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en/settings/two_factor.php' => ['title' => 'Two-Factor'],
        ]);

        expect(TranslationLoader::load('en', '*'))
            ->toHaveKey('nav.home', 'Home')
            ->toHaveKey('settings.two_factor.title', 'Two-Factor');
    });

    it("returns empty array for '*' when locale directory does not exist", function () {
        $this->langBase = withLangFiles([]);

        expect(TranslationLoader::load('xx', '*'))->toBe([]);
    });

    it("ignores non-PHP files when loading '*'", function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir("{$base}/en", 0755, true);
        file_put_contents("{$base}/en/nav.php", "<?php\nreturn ['home' => 'Home'];");
        file_put_contents("{$base}/en/README.md", '# Translations');
        file_put_contents("{$base}/en/nav.json", '{"home":"Home"}');
        app()->useLangPath($base);
        $this->langBase = $base;

        expect(TranslationLoader::load('en', '*'))->toBe([
            'nav.home' => 'Home',
        ]);
    });

    it("treats '*' the same regardless of other groups also being passed", function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en/auth.php' => ['login' => 'Login'],
        ]);

        $wildcard = TranslationLoader::load('en', '*');
        $explicit = TranslationLoader::load('en', '*', 'nav');

        expect($wildcard)->toBe($explicit);
    });

});

// ---------------------------------------------------------------------------
// load() — locale isolation
// ---------------------------------------------------------------------------

describe('load() locale isolation', function () {

    it('loads the correct locale', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'it/nav.php' => ['home' => 'Casa'],
        ]);

        expect(TranslationLoader::load('en', 'nav'))->toBe(['nav.home' => 'Home']);
        expect(TranslationLoader::load('it', 'nav'))->toBe(['nav.home' => 'Casa']);
    });

    it('does not bleed keys across locales', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home', 'about' => 'About'],
            'it/nav.php' => ['home' => 'Casa'],
        ]);
        config(['inertia-i18n.fallback' => false]);

        expect(TranslationLoader::load('it', 'nav'))->not->toHaveKey('nav.about');
    });

    it('returns empty array when locale has no files', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
        ]);
        config(['inertia-i18n.fallback' => false]);

        expect(TranslationLoader::load('fr', 'nav'))->toBe([]);
    });

});

// ---------------------------------------------------------------------------
// Fallback locale
// ---------------------------------------------------------------------------

describe('load() fallback locale', function () {

    beforeEach(fn () => app('translator')->setFallback('en'));

    it('fills keys missing from the active locale with fallback values', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home', 'about' => 'About'],
            'it/nav.php' => ['home' => 'Casa'],
        ]);

        expect(TranslationLoader::load('it', 'nav'))->toEqual([
            'nav.home' => 'Casa',
            'nav.about' => 'About',
        ]);
    });

    it('keeps empty strings and "0" from the active locale', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['a' => 'A', 'b' => 'B'],
            'it/nav.php' => ['a' => '', 'b' => '0'],
        ]);

        expect(TranslationLoader::load('it', 'nav'))->toBe(['nav.a' => '', 'nav.b' => '0']);
    });

    it('includes groups that exist only in the fallback locale when using *', function () {
        $this->langBase = withLangFiles([
            'en/only_en.php' => ['x' => 'Fallback'],
            'it/nav.php' => ['home' => 'Casa'],
        ]);

        expect(TranslationLoader::load('it', '*'))->toEqual([
            'only_en.x' => 'Fallback',
            'nav.home' => 'Casa',
        ]);
    });

    it('can be disabled with the fallback option', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
        ]);
        config(['inertia-i18n.fallback' => false]);

        expect(TranslationLoader::load('it', 'nav'))->toBe([]);
    });

});

// ---------------------------------------------------------------------------
// Standard lang/{locale}.json catalog
// ---------------------------------------------------------------------------

describe("load() '@json' catalog", function () {

    it('loads lang/{locale}.json with literal keys and string values only', function () {
        $this->langBase = withLangFiles([
            'en.json' => ['Welcome, :name' => 'Welcome, :name', 'dotted.key' => 'Dotted', 'skip' => 4, 'nested' => ['x' => 'y']],
        ]);

        expect(TranslationLoader::load('en', '@json'))->toBe([
            'Welcome, :name' => 'Welcome, :name',
            'dotted.key' => 'Dotted',
        ]);
    });

    it('preserves integer-like keys', function () {
        $this->langBase = withLangFiles([
            'en.json' => '{"404": "Not Found", "500": "Server Error"}',
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('en', 'nav', '@json'))->toBe([
            'nav.home' => 'Home',
            404 => 'Not Found',
            500 => 'Server Error',
        ]);
    });

    it("is not included by '*' unless requested", function () {
        $this->langBase = withLangFiles([
            'en.json' => ['Hello' => 'Hello'],
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('en', '*'))->toBe(['nav.home' => 'Home']);
        expect(TranslationLoader::load('en', '*', '@json'))->toBe(['nav.home' => 'Home', 'Hello' => 'Hello']);
    });

    it('falls back to the fallback locale catalog', function () {
        app('translator')->setFallback('en');
        $this->langBase = withLangFiles([
            'en.json' => ['Hello' => 'Hello', 'Bye' => 'Bye'],
            'it.json' => ['Hello' => 'Ciao'],
        ]);

        expect(TranslationLoader::load('it', '@json'))->toEqual(['Hello' => 'Ciao', 'Bye' => 'Bye']);
    });

});

// ---------------------------------------------------------------------------
// Invalid input
// ---------------------------------------------------------------------------

describe('load() invalid input', function () {

    it('throws on malformed JSON instead of silently dropping the catalog', function () {
        $this->langBase = withLangFiles(['en/broken.json' => '{"a": ']);

        expect(fn () => TranslationLoader::load('en', 'broken'))
            ->toThrow(RuntimeException::class, 'broken.json] contains an invalid JSON structure');
    });

    it('treats an empty JSON list or a scalar as an empty catalog', function () {
        $this->langBase = withLangFiles([
            'en/empty.json' => '[]',
            'en/scalar.json' => '"text"',
            'en.json' => '[]',
        ]);

        expect(TranslationLoader::load('en', 'empty', 'scalar', '@json'))->toBe([]);
    });

    it('rejects locales that could escape the lang directory', function (string $locale) {
        $this->langBase = withLangFiles(['en/nav.php' => ['home' => 'Home']]);

        expect(fn () => TranslationLoader::load($locale, '*'))->toThrow(InvalidArgumentException::class);
    })->with(['..', '../en', 'en/../en', 'en\\x', "en\0", '']);

    it('never executes files outside the locale directory for a traversal locale', function () {
        $this->langBase = withLangFiles([]);
        $marker = $this->langBase.'/executed';
        file_put_contents($this->langBase.'/outside.php', "<?php file_put_contents('{$marker}', 'x'); return [];");
        app()->useLangPath($this->langBase.'/lang');

        expect(fn () => TranslationLoader::load('..', '*'))->toThrow(InvalidArgumentException::class);
        expect(file_exists($marker))->toBeFalse();
    });

    it('accepts locale codes with regions and variants', function () {
        $this->langBase = withLangFiles([
            'pt_BR/nav.php' => ['home' => 'Início'],
            'sr@latin/nav.php' => ['home' => 'Početna'],
        ]);

        expect(TranslationLoader::load('pt_BR', 'nav'))->toBe(['nav.home' => 'Início']);
        expect(TranslationLoader::load('sr@latin', 'nav'))->toBe(['nav.home' => 'Početna']);
    });

    it('still accepts slash-separated group names', function () {
        $this->langBase = withLangFiles(['en/settings/two_factor.php' => ['title' => 'Two-Factor']]);

        expect(TranslationLoader::load('en', 'settings/two_factor'))->toBe([
            'settings/two_factor.title' => 'Two-Factor',
        ]);
    });

});

// ---------------------------------------------------------------------------
// Plural parity: the same vectors run against the TypeScript helper
// (tests/js/useTranslation.test.mjs), so both sides choose the same form.
// ---------------------------------------------------------------------------

it('agrees with Laravel MessageSelector on the shared plural vectors', function () {
    $vectors = json_decode(file_get_contents(__DIR__.'/fixtures/plural-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
    $selector = new MessageSelector;

    foreach ($vectors as ['line' => $line, 'locale' => $locale, 'count' => $count, 'expected' => $expected]) {
        expect($selector->choose($line, $count, $locale))->toBe($expected, "{$locale} / {$count} / {$line}");
    }
});
