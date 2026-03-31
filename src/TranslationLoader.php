<?php

namespace AlecsCodes\InertiaI18n;

use Illuminate\Support\Arr;

final class TranslationLoader
{
    /**
     * Load and flatten translation groups for a given locale.
     *
     * Pass '*' as a group to load every PHP file in the locale directory.
     *
     * @param  string  $locale  e.g. 'en', 'it'
     * @param  string  ...$groups  e.g. 'auth', 'settings.two_factor', or '*' for all
     * @return array<string, string>
     */
    public static function load(string $locale, string ...$groups): array
    {
        $groups = array_unique($groups);

        if (in_array('*', $groups, true)) {
            return self::loadAll($locale);
        }

        $loaded = array_map(
            fn (string $group) => self::loadGroup($locale, $group),
            $groups,
        );

        return $loaded === [] ? [] : array_merge(...$loaded);
    }

    /**
     * Load every PHP translation file found for the given locale,
     * including files nested in subdirectories.
     *
     * e.g. lang/en/settings/two_factor.php -> key prefix 'settings.two_factor'
     *
     * @return array<string, string>
     */
    private static function loadAll(string $locale): array
    {
        $base = lang_path($locale);

        if (! is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        $results = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['php', 'json'], true)) {
                continue;
            }

            // Derive the dot-notation group from the path relative to the locale dir.
            // e.g. settings/two_factor.php -> 'settings.two_factor'
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $withoutExtension = preg_replace('/\.(php|json)$/', '', $relative);
            $group = str_replace('/', '.', (string) $withoutExtension);

            $results = [...$results, ...self::loadGroup($locale, $group)];
        }

        return $results;
    }

    /**
     * Load a single group and flatten it to dot-notation keys.
     *
     * Accepts dot-notation ('settings.two_factor') — dots are converted to
     * directory separators for the filesystem path, and the dot-notation form
     * is used as the key prefix so all keys are consistent dot-notation.
     *
     * e.g. group 'settings.two_factor' -> file 'lang/en/settings/two_factor.php'
     *                                   -> keys 'settings.two_factor.title', ...
     *
     * @return array<string, string>
     */
    private static function loadGroup(string $locale, string $group): array
    {
        // Dots -> slashes for filesystem, keep original as key prefix
        $basePath = lang_path($locale.'/'.str_replace('.', '/', $group));

        $phpPath = $basePath.'.php';
        if (is_file($phpPath)) {
            $lines = require $phpPath;

            return is_array($lines) ? self::flatten($lines, $group) : [];
        }

        $jsonPath = $basePath.'.json';
        if (is_file($jsonPath)) {
            $contents = file_get_contents($jsonPath);
            if ($contents === false) {
                return [];
            }

            $decoded = json_decode($contents, true);

            return is_array($decoded) ? self::flatten($decoded, $group) : [];
        }

        return [];
    }

    /**
     * Recursively flatten a nested array into dot-notation string entries only.
     *
     * Non-string leaves (integers, nulls, etc.) are silently skipped so the
     * return type is guaranteed to be array<string, string>.
     *
     * @param  array<string, mixed>  $items
     * @return array<string, string>
     */
    private static function flatten(array $items, string $prefix = ''): array
    {
        // Arr::dot from Laravel already does recursive dot-notation flattening,
        // but it keeps non-string values. We filter those out in one pass.
        $dotted = Arr::dot($items, $prefix ? "{$prefix}." : '');

        return array_filter($dotted, 'is_string');
    }
}
