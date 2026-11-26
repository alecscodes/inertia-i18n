<?php

namespace AlecsCodes\InertiaI18n;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

final class InertiaI18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-i18n.php', 'inertia-i18n');
    }

    public function boot(): void
    {
        $this->configurePublishing();
        $this->configureInertia();
        InertiaMacros::register();
    }

    private function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/js/useTranslation.ts' => resource_path('js/vendor/inertia-i18n/useTranslation.ts'),
            ], 'inertia-i18n-js');

            $this->publishes([
                __DIR__.'/../config/inertia-i18n.php' => config_path('inertia-i18n.php'),
            ], 'inertia-i18n-config');
        }
    }

    private function configureInertia(): void
    {
        $localeProp = config('inertia-i18n.props.locale', 'locale');
        $transProp = config('inertia-i18n.props.translations', 'translations');
        $localesProp = config('inertia-i18n.props.translation_locales', 'translationLocales');
        $resolveShared = static function (): ?array {
            $request = app('request');
            $locale = app()->getLocale();
            $shared = (array) config('inertia-i18n.shared', []);
            $fallbackEnabled = (bool) config('inertia-i18n.fallback', true);
            $fallbackLocale = app('translator')->getFallback();
            $langRoot = realpath(lang_path()) ?: lang_path();
            $inputKey = hash('sha256', serialize([$locale, $shared, $fallbackEnabled, $fallbackLocale, $langRoot]));
            $cacheKey = '__inertia_i18n_shared_results';
            $cache = (array) $request->attributes->get($cacheKey, []);
            if (array_key_exists($inputKey, $cache)) {
                return $cache[$inputKey];
            }
            $result = empty($shared)
                ? null
                : TranslationLoader::loadWithMetadata($locale, ...$shared);
            $cache[$inputKey] = $result;
            $request->attributes->set($cacheKey, $cache);
            return $result;
        };

        Inertia::share([
            $localeProp => static fn (): string => app()->getLocale(),

            $transProp => static function () use ($resolveShared): array|object|null {
                $result = $resolveShared();
                if ($result === null) {
                    return null;
                }
                $catalog = $result['translations'];
                return $catalog === [] ? [] : (object) $catalog;
            },

            $localesProp => static function () use ($resolveShared): array|object|null {
                $result = $resolveShared();
                if ($result === null) {
                    return null;
                }
                $locales = $result['translationLocales'];
                return $locales === [] ? [] : (object) $locales;
            },
        ]);
    }
}
