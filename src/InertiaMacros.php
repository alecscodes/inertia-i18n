<?php

namespace AlecsCodes\InertiaI18n;

use Inertia\Response;

final class InertiaMacros
{
    public static function register(): void
    {
        Response::macro('withTranslations', self::withTranslationsMacro());
    }

    private static function withTranslationsMacro(): \Closure
    {
        return function (string ...$groups): Response {
            /** @var Response $this */
            $prop = config('inertia-i18n.props.translations', 'translations');
            $localesProp = config('inertia-i18n.props.translation_locales', 'translationLocales');

            // Config and locale are intentionally resolved when Inertia evaluates
            // these props, not when the response or macro is constructed.
            $results = [];
            $resolve = static function () use (&$results, $groups): array {
                $shared = (array) config('inertia-i18n.shared', []);
                $merged = array_values(array_unique([...$shared, ...$groups]));
                $locale = app()->getLocale();
                $fallbackEnabled = (bool) config('inertia-i18n.fallback', true);
                $fallbackLocale = app('translator')->getFallback();
                $langRoot = realpath(lang_path()) ?: lang_path();
                $cacheKey = hash('sha256', serialize([$locale, $merged, $fallbackEnabled, $fallbackLocale, $langRoot]));
                if (! array_key_exists($cacheKey, $results)) {
                    $results[$cacheKey] = TranslationLoader::loadWithMetadata($locale, ...$merged);
                }
                return $results[$cacheKey];
            };

            return $this->with([
                $prop => static function () use ($resolve): array|object {
                    $catalog = $resolve()['translations'];
                    return $catalog === [] ? [] : (object) $catalog;
                },
                $localesProp => static function () use ($resolve): array|object {
                    $locales = $resolve()['translationLocales'];
                    return $locales === [] ? [] : (object) $locales;
                },
            ]);
        };
    }
}
