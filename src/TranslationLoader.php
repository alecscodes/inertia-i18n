<?php

namespace AlecsCodes\InertiaI18n;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

/** Loads this package's framework-neutral string catalog from Laravel lang files. */
final class TranslationLoader
{
    /** @return array<array-key, string> */
    public static function load(string $locale, string ...$groups): array
    {
        return self::loadWithMetadata($locale, ...$groups)['translations'];
    }

    /**
     * Load requested groups. `@json` opts in to lang/{locale}.json; `*` loads
     * all local groups and that JSON catalog. Local active-locale values
     * override fallback-locale values, regardless of file format.
     *
     * @return array{translations: array<array-key,string>, translationLocales: array<array-key,string>}
     */
    public static function loadWithMetadata(string $locale, string ...$groups): array
    {
        self::validateLocale($locale);
        foreach ($groups as $group) {
            self::validateGroup($group);
        }

        $groups = array_values(array_unique($groups));
        $wildcard = in_array('*', $groups, true);
        $json = $wildcard || in_array('@json', $groups, true);
        $groups = array_values(array_filter($groups, static fn (string $group): bool => ! in_array($group, ['*', '@json'], true)));

        $fallback = config('inertia-i18n.fallback', true) ? app('translator')->getFallback() : null;
        if (is_string($fallback) && $fallback !== '') {
            self::validateLocale($fallback);
        } else {
            $fallback = null;
        }

        if ($wildcard) {
            $groups = array_values(array_unique([...$groups, ...self::discoverGroups($locale), ...($fallback && $fallback !== $locale ? self::discoverGroups($fallback) : [])]));
            sort($groups, SORT_STRING);
        }

        $active = self::loadLocale($locale, $groups, $json);
        $resolved = $active;
        $sources = [];
        if ($fallback !== null && $fallback !== $locale) {
            $fallbackValues = self::loadLocale($fallback, $groups, $json);
            $resolved = $fallbackValues;
            foreach ($fallbackValues as $key => $_) {
                $sources[$key] = $fallback;
            }
            // Active locale has priority over every fallback format.
            $resolved = array_replace($resolved, $active);
            foreach ($active as $key => $_) {
                unset($sources[$key]);
            }
        }

        return ['translations' => $resolved, 'translationLocales' => $sources];
    }

    private static function validateLocale(string $locale): void
    {
        if ($locale === '' || ! preg_match('/\A[A-Za-z0-9_-]+\z/D', $locale)) {
            throw new InvalidArgumentException('Invalid locale: expected a non-empty locale code.');
        }
    }

    private static function validateGroup(string $group): void
    {
        if ($group === '*' || $group === '@json') {
            return;
        }
        if ($group === '' || ! preg_match('/\A[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z/D', $group)) {
            throw new InvalidArgumentException('Invalid translation group: expected dot-separated group segments.');
        }
    }

    /** @return list<string> */
    private static function discoverGroups(string $locale): array
    {
        $dir = self::localeDirectory($locale);
        if ($dir === null) {
            return [];
        }
        $groups = [];
        $walk = static function (string $directory, string $prefix = '') use (&$walk, &$groups, $dir): void {
            $entries = scandir($directory);
            if (! is_array($entries)) {
                return;
            }
            sort($entries, SORT_STRING);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $directory.DIRECTORY_SEPARATOR.$entry;
                // Deliberately do not descend through symlinked directories.
                if (is_link($path)) {
                    continue;
                }
                if (is_dir($path)) {
                    $walk($path, $prefix === '' ? $entry : $prefix.'.'.$entry);
                    continue;
                }
                if (preg_match('/\A(.+)\.(?:php|json)\z/D', $entry, $match)) {
                    $name = $prefix === '' ? $match[1] : $prefix.'.'.$match[1];
                    if (preg_match('/\A[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z/D', $name)) {
                        $groups[$name] = true;
                    }
                }
            }
        };
        $walk($dir);
        return array_keys($groups);
    }

    /** @param list<string> $groups @return array<array-key,string> */
    private static function loadLocale(string $locale, array $groups, bool $json): array
    {
        $result = [];
        foreach ($groups as $group) {
            $result = array_replace($result, self::loadGroup($locale, $group));
        }
        if ($json) {
            $result = array_replace($result, self::loadStandardJson($locale));
        }
        return $result;
    }

    /** @return array<array-key,string> */
    private static function loadGroup(string $locale, string $group): array
    {
        $dir = self::localeDirectory($locale);
        if ($dir === null) {
            return [];
        }
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $group);
        $php = self::safeFile($dir, $relative.'.php');
        if ($php !== null) {
            $lines = require $php;
            return is_array($lines) ? self::flatten($lines, $group) : [];
        }
        $json = self::safeFile($dir, $relative.'.json');
        if ($json === null) {
            return [];
        }
        return self::flatten(self::objectToArray(self::decodeObject($json)), $group);
    }

    /** @return array<array-key,string> */
    private static function loadStandardJson(string $locale): array
    {
        $root = self::languageRoot();
        if ($root === null) {
            return [];
        }
        $file = self::safeFile($root, $locale.'.json');
        if ($file === null) {
            return [];
        }
        $decoded = self::decodeObject($file);
        $strings = [];
        foreach ($decoded as $key => $value) {
            if (is_string($value)) {
                // PHP coerces canonical numeric string array keys to integers;
                // that remains a valid array API key, and the Inertia boundary
                // casts dictionaries to objects to preserve their wire shape.
                $strings[$key] = $value;
            }
        }
        return $strings;
    }

    private static function decodeObject(string $file): \stdClass
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read translation JSON file: {$file}");
        }
        try {
            $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Malformed translation JSON in {$file}: {$exception->getMessage()}", 0, $exception);
        }
        if (! $decoded instanceof \stdClass) {
            throw new RuntimeException("Translation JSON must contain an object: {$file}");
        }
        return $decoded;
    }

    /** @return array<string,string> */
    private static function flatten(array $items, string $prefix): array
    {
        $dotted = Arr::dot($items, $prefix.'.');
        return array_filter($dotted, 'is_string');
    }

    /** @return array<mixed> */
    private static function objectToArray(\stdClass $object): array
    {
        $items = [];
        foreach ($object as $key => $value) {
            $items[$key] = self::convertJsonValue($value);
        }
        return $items;
    }

    private static function convertJsonValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return self::objectToArray($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::convertJsonValue($item);
            }
        }
        return $value;
    }

    private static function languageRoot(): ?string
    {
        $root = realpath(lang_path());
        return $root !== false && is_dir($root) ? $root : null;
    }

    private static function localeDirectory(string $locale): ?string
    {
        $root = self::languageRoot();
        if ($root === null) {
            return null;
        }
        return self::safeDirectory($root, $locale);
    }

    private static function safeDirectory(string $root, string $relative): ?string
    {
        if (self::hasSymlinkComponent($root, $relative)) {
            return null;
        }
        $path = $root.DIRECTORY_SEPARATOR.$relative;
        $real = realpath($path);
        return $real !== false && is_dir($real) && self::within($root, $real) ? $real : null;
    }

    private static function safeFile(string $root, string $relative): ?string
    {
        if (self::hasSymlinkComponent($root, $relative)) {
            return null;
        }
        $path = $root.DIRECTORY_SEPARATOR.$relative;
        $real = realpath($path);
        return $real !== false && is_file($real) && self::within($root, $real) ? $real : null;
    }

    private static function hasSymlinkComponent(string $root, string $relative): bool
    {
        $path = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            $path .= DIRECTORY_SEPARATOR.$part;
            if (is_link($path)) {
                return true;
            }
        }
        return false;
    }

    private static function within(string $root, string $path): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
