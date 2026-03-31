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
        $shared = (array) config('inertia-i18n.shared', []);

        Inertia::share([
            // Always share the current locale
            $localeProp => app()->getLocale(),

            // Share shared translations on every response.
            // Returns null (shared as nothing) when no shared groups are configured,
            // so the prop is omitted entirely rather than sent as an empty object.
            $transProp => static function () use ($shared): ?array {
                if (empty($shared)) {
                    return null;
                }

                $locale = app()->getLocale();

                return TranslationLoader::load($locale, ...$shared) ?: null;
            },
        ]);
    }
}
