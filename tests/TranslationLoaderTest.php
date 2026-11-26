<?php

use AlecsCodes\InertiaI18n\TranslationLoader;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

afterEach(function () {
    if (isset($this->langBase) && is_dir($this->langBase)) {
        File::deleteDirectory($this->langBase);
    }
});

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

    it('loads standard JSON only through @json or wildcard, preserving literal keys', function () {
        $this->langBase = withLangFiles([
            'en.json' => [' dotted.key ' => 'Literal', 'plain' => 'Plain', 'ignored' => 4],
        ]);
        expect(TranslationLoader::load('en'))->toBe([]);
        expect(TranslationLoader::load('en', '@json'))->toBe([' dotted.key ' => 'Literal', 'plain' => 'Plain']);
        expect(TranslationLoader::load('en', '*'))->toBe([' dotted.key ' => 'Literal', 'plain' => 'Plain']);
    });

    it('flattens nested grouped JSON but keeps standard JSON keys literal and ignores nested values', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base.'/en', 0755, true);
        file_put_contents($base.'/en/messages.json', '{"nested":{"label":"Hello","skip":3,"array":["yes",false,{"deep":"No"}]}}');
        file_put_contents($base.'/en.json', '{"top":{"nested":"ignored"},"ok":"Yes"}');
        app()->useLangPath($base);
        $this->langBase = $base;
        expect(TranslationLoader::load('en', 'messages'))->toBe([
            'messages.nested.label' => 'Hello',
            'messages.nested.array.0' => 'yes',
            'messages.nested.array.2.deep' => 'No',
        ]);
        expect(TranslationLoader::load('en', '@json'))->toBe(['ok' => 'Yes']);
    });

    it('accepts an empty JSON object and rejects scalar, null, list, malformed grouped JSON with scoped errors', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base.'/en', 0755, true);
        app()->useLangPath($base);
        $this->langBase = $base;
        file_put_contents($base.'/en.json', '{}');
        expect(TranslationLoader::load('en', '@json'))->toBe([]);
        foreach (['null', '42', '"scalar"', '[]'] as $invalid) {
            file_put_contents($base.'/en.json', $invalid);
            expect(fn () => TranslationLoader::load('en', '@json'))->toThrow(RuntimeException::class, 'must contain an object');
        }
        file_put_contents($base.'/en/messages.json', '{broken');
        try {
            TranslationLoader::load('en', 'messages');
            test()->fail('Expected malformed grouped JSON to throw.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toContain($base.'/en/messages.json')
                ->and($exception->getMessage())->not->toContain('{broken');
        }
    });

    it('uses locale priority before format priority and tracks only fallback provenance', function () {
        $this->langBase = withLangFiles([
            'en/common.php' => ['same' => 'fallback-group', 'fallback' => 'Fallback', 'active_wins' => 'fallback-value'],
            'en.json' => ['common.same' => 'fallback-json', 'rootOnly' => 'Root fallback', 'zero' => 'fallback zero'],
            'fr/common.php' => ['same' => 'active-group', 'active_wins' => ''],
            'fr.json' => ['common.same' => 'active-json', 'local' => 'Local', 'zero' => '0'],
        ]);
        app('translator')->setFallback('en');
        $result = TranslationLoader::loadWithMetadata('fr', '*');
        $translations = $result['translations'];
        $expectedTranslations = [
            'common.fallback' => 'Fallback',
            'common.same' => 'active-json',
            'common.active_wins' => '',
            'rootOnly' => 'Root fallback',
            'local' => 'Local',
            'zero' => '0',
        ];
        ksort($translations);
        ksort($expectedTranslations);
        expect($translations)->toBe($expectedTranslations);
        expect($result['translationLocales'])->toBe(['common.fallback' => 'en', 'rootOnly' => 'en']);
    });

    it('lets active grouped values override fallback root JSON while same-locale root JSON overrides groups', function () {
        $this->langBase = withLangFiles([
            'en.json' => ['common.same' => 'fallback root'],
            'fr/common.php' => ['same' => 'active grouped'],
            'fr.json' => ['common.same' => 'active root'],
        ]);
        app('translator')->setFallback('en');
        $withJson = TranslationLoader::load('fr', 'common', '@json');
        expect($withJson['common.same'])->toBe('active root');
        file_put_contents($this->langBase.'/fr.json', '{}');
        $groupOnly = TranslationLoader::loadWithMetadata('fr', 'common');
        expect($groupOnly['translations']['common.same'])->toBe('active grouped');
        expect($groupOnly['translationLocales'])->toBe([]);
    });

    it('includes fallback-only wildcard groups, handles equal fallback locale, and reads each discovered PHP group once', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base.'/en', 0755, true);
        mkdir($base.'/fr', 0755, true);
        $counter = $base.'/counter';
        file_put_contents($base.'/en/only.php', "<?php file_put_contents(".var_export($counter, true).", 'x', FILE_APPEND); return ['v' => 'fallback'];");
        file_put_contents($base.'/fr/local.php', "<?php file_put_contents(".var_export($counter, true).", 'y', FILE_APPEND); return ['v' => 'local'];");
        app()->useLangPath($base);
        $this->langBase = $base;
        app('translator')->setFallback('en');
        $result = TranslationLoader::loadWithMetadata('fr', '*', 'only', 'only');
        expect($result['translations'])->toMatchArray([
            'only.v' => 'fallback',
            'local.v' => 'local',
        ]);
        expect($result['translationLocales'])->toBe(['only.v' => 'en']);
        expect(file_get_contents($counter))->toBe('yx');

        app('translator')->setFallback('fr');
        expect(TranslationLoader::loadWithMetadata('fr', 'local')['translationLocales'])->toBe([]);
    });

    it('uses sorted wildcard group order for a real flattened-key collision', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        mkdir($base.'/en/a', 0755, true);
        // Create the later-sorted group first to ensure directory creation order is irrelevant.
        file_put_contents($base.'/en/a/b.php', "<?php return ['c' => 'from nested group'];");
        file_put_contents($base.'/en/a.php', "<?php return ['b' => ['c' => 'from parent group']];");
        app()->useLangPath($base);
        $this->langBase = $base;

        expect(TranslationLoader::load('en', '*'))->toBe(['a.b.c' => 'from nested group']);
    });

    it('supports explicit groups without implicitly including standard JSON and honors fallback opt-out', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
            'en.json' => ['site' => 'Site'],
        ]);
        app('translator')->setFallback('en');
        expect(TranslationLoader::loadWithMetadata('fr', 'nav'))->toBe([
            'translations' => ['nav.home' => 'Home'],
            'translationLocales' => ['nav.home' => 'en'],
        ]);
        config(['inertia-i18n.fallback' => false]);
        expect(TranslationLoader::loadWithMetadata('fr', 'nav')['translations'])->toBe([]);
    });

    it('prefers PHP over grouped JSON and lets later explicitly requested groups win collisions', function () {
        $this->langBase = withLangFiles([
            'en/one.php' => ['a' => ['x' => 'PHP']],
            'en/one.json' => ['x' => 'JSON'],
            'en/one/a.php' => ['x' => 'Later'],
        ]);
        expect(TranslationLoader::load('en', 'one', 'one.a'))->toBe(['one.a.x' => 'Later']);
    });

    it('rejects invalid selectors and malformed or non-object JSON visibly', function () {
        $this->langBase = withLangFiles(['en.json' => ['ok' => 'yes']]);
        expect(fn () => TranslationLoader::load('../en', '*'))->toThrow(InvalidArgumentException::class);
        expect(fn () => TranslationLoader::load('en', 'a/../b'))->toThrow(InvalidArgumentException::class);
        foreach (["a\\b", '/absolute', "a\0b", 'php://filter', 'C:drive'] as $group) {
            expect(fn () => TranslationLoader::load('en', $group))->toThrow(InvalidArgumentException::class);
        }
        foreach (["../en", 'C:\\locale', "en\0bad", 'en/../fr', 'php://filter'] as $locale) {
            expect(fn () => TranslationLoader::load($locale, '*'))->toThrow(InvalidArgumentException::class);
        }
        expect(fn () => TranslationLoader::load('en', ''))->toThrow(InvalidArgumentException::class);
        file_put_contents($this->langBase.'/en.json', '["not", "object"]');
        expect(fn () => TranslationLoader::load('en', '@json'))->toThrow(RuntimeException::class, 'must contain an object');
        file_put_contents($this->langBase.'/en.json', '{broken');
        expect(fn () => TranslationLoader::load('en', '@json'))->toThrow(RuntimeException::class, 'Malformed translation JSON');
    });

    it('does not follow symlink directories during wildcard discovery', function () {
        $this->langBase = withLangFiles(['en/own.php' => ['x' => 'Own']]);
        $outside = sys_get_temp_dir().'/inertia-i18n-outside-'.uniqid();
        mkdir($outside);
        file_put_contents($outside.'/secret.php', "<?php return ['x' => 'secret'];");
        symlink($outside, $this->langBase.'/en/linked');
        expect(TranslationLoader::load('en', '*'))->toBe(['own.x' => 'Own']);
        File::deleteDirectory($outside);
    });

    it('rejects explicit symlink paths, locale symlinks, and standard JSON symlinks outside the lang root', function () {
        $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
        $outside = sys_get_temp_dir().'/inertia-i18n-outside-'.uniqid();
        mkdir($base.'/en', 0755, true);
        mkdir($outside, 0755, true);
        $marker = $outside.'/executed';
        file_put_contents($outside.'/secret.php', "<?php file_put_contents(".var_export($marker, true).", 'yes'); return ['x'=>'secret'];");
        file_put_contents($outside.'/translations.json', '{"x":"secret"}');
        symlink($outside, $base.'/en/linked');
        symlink($outside.'/secret.php', $base.'/en/evil.php');
        symlink($outside.'/translations.json', $base.'/fr.json');
        symlink($outside, $base.'/de');
        app()->useLangPath($base);
        $this->langBase = $base;

        expect(TranslationLoader::load('en', 'linked.secret'))->toBe([]);
        expect(TranslationLoader::load('en', 'evil'))->toBe([]);
        expect(TranslationLoader::load('fr', '@json'))->toBe([]);
        expect(TranslationLoader::load('de', '*'))->toBe([]);
        expect(file_exists($marker))->toBeFalse();
        File::deleteDirectory($outside);
    });

});

it('matches Laravel MessageSelector for the shared plural parity vectors', function () {
    $vectors = json_decode(file_get_contents(__DIR__.'/fixtures/plural-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    $selector = new \Illuminate\Translation\MessageSelector;

    foreach ($vectors as $vector) {
        expect($selector->choose($vector['line'], $vector['count'], $vector['locale']))
            ->toBe($vector['expected']);
    }
});
