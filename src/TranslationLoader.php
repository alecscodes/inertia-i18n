<?php

namespace AlecsCodes\InertiaI18n;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

final class TranslationLoader
{
    /**
     * Load and flatten translation groups for a given locale.
     *
     * Groups use dot-notation for subdirectories ('settings.two_factor').
     * Pass '*' to load every file in lang/{locale}/, and '@json' to include
     * the standard lang/{locale}.json catalog (its keys are kept literally).
     *
     * When the 'inertia-i18n.fallback' option is enabled, keys missing from
     * the requested locale are filled in from Laravel's fallback locale.
     *
     * @param  string  $locale  e.g. 'en', 'it'
     * @param  string  ...$groups  e.g. 'auth', 'settings.two_factor', '*', '@json'
     * @return array<array-key, string>
     */
    public static function load(string $locale, string ...$groups): array
    {
        $groups = array_values(array_unique($groups));
        $translations = self::loadLocale($locale, $groups);

        $fallback = config('inertia-i18n.fallback', true) ? app('translator')->getFallback() : null;

        if (is_string($fallback) && $fallback !== '' && $fallback !== $locale) {
            // Active-locale values win over fallback values.
            $translations = array_replace(self::loadLocale($fallback, $groups), $translations);
        }

        return $translations;
    }

    /**
     * @param  list<string>  $groups
     * @return array<array-key, string>
     */
    private static function loadLocale(string $locale, array $groups): array
    {
        self::assertValidLocale($locale);

        // '*' already covers every group in the locale directory.
        if (in_array('*', $groups, true)) {
            $groups = array_values(array_intersect($groups, ['*', '@json']));
        }

        $results = [];

        foreach ($groups as $group) {
            // array_replace (not array_merge) so integer-like JSON keys are not renumbered.
            $results = array_replace($results, match ($group) {
                '*' => self::loadAll($locale),
                '@json' => self::loadJsonCatalog($locale),
                default => self::loadGroup($locale, $group),
            });
        }

        return $results;
    }

    /**
     * Same rule Laravel's Translator::setLocale() applies: the locale is used
     * as a path segment, so it must not be able to leave the lang directory.
     */
    private static function assertValidLocale(string $locale): void
    {
        if ($locale === '' || str_contains($locale, '/') || str_contains($locale, '\\')
            || str_contains($locale, '..') || str_contains($locale, "\0")) {
            throw new InvalidArgumentException('Invalid characters present in locale.');
        }
    }

    /**
     * Load every PHP/JSON file found for the given locale, including files
     * nested in subdirectories (lang/en/settings/two_factor.php -> 'settings.two_factor').
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

        $groups = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['php', 'json'], true)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $groups[] = str_replace('/', '.', (string) preg_replace('/\.(php|json)$/', '', $relative));
        }

        // Directory iteration order is filesystem-dependent; sort for stable output.
        $groups = array_unique($groups);
        sort($groups, SORT_STRING);

        $results = [];

        foreach ($groups as $group) {
            $results = array_replace($results, self::loadGroup($locale, $group));
        }

        return $results;
    }

    /**
     * Load a single group and flatten it to dot-notation keys prefixed with
     * the group name. A PHP file takes precedence over a JSON file of the
     * same name.
     *
     * @return array<string, string>
     */
    private static function loadGroup(string $locale, string $group): array
    {
        $basePath = lang_path($locale.'/'.str_replace('.', '/', $group));

        if (is_file($basePath.'.php')) {
            $lines = require $basePath.'.php';

            return is_array($lines) ? self::flatten($lines, $group) : [];
        }

        if (is_file($basePath.'.json')) {
            return self::flatten(self::decodeJson($basePath.'.json'), $group);
        }

        return [];
    }

    /**
     * Load lang/{locale}.json. Keys are kept as-is (they are usually the
     * source strings themselves) and only string values are kept.
     *
     * @return array<array-key, string>
     */
    private static function loadJsonCatalog(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return is_file($path) ? array_filter(self::decodeJson($path), 'is_string') : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeJson(string $path): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Mirrors Laravel's FileLoader: a broken catalog should be visible, not silently empty.
            throw new RuntimeException("Translation file [{$path}] contains an invalid JSON structure.", 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Flatten a nested array into dot-notation keys, keeping string leaves only.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<string, string>
     */
    private static function flatten(array $items, string $prefix): array
    {
        return array_filter(Arr::dot($items, $prefix.'.'), 'is_string');
    }
}
