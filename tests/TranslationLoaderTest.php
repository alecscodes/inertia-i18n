<?php

use AlecsCodes\InertiaI18n\TranslationLoader;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a temporary lang directory structure and point Laravel's lang_path()
 * to it. Returns the base path so afterEach() can clean it up.
 */
function withLangFiles(array $structure): string
{
    $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
    mkdir($base, 0755, true);

    foreach ($structure as $path => $content) {
        $full = "{$base}/{$path}";
        $dir  = dirname($full);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (str_ends_with($path, '.json')) {
            file_put_contents($full, json_encode($content, JSON_PRETTY_PRINT));
            continue;
        }

        file_put_contents($full, "<?php\nreturn ".var_export($content, true).';');
    }

    app()->useLangPath($base);

    return $base;
}

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

        expect(TranslationLoader::load('it', 'nav'))->not->toHaveKey('nav.about');
    });

    it('returns empty array when locale has no files', function () {
        $this->langBase = withLangFiles([
            'en/nav.php' => ['home' => 'Home'],
        ]);

        expect(TranslationLoader::load('fr', 'nav'))->toBe([]);
    });

});
