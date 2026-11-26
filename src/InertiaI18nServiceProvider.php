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

        // Both props are closures so they are resolved when the response is
        // rendered — after middleware has set the request's locale — and are
        // skipped entirely by partial reloads that don't ask for them.
        Inertia::share([
            $localeProp => static fn (): string => app()->getLocale(),

            // null when no shared groups are configured (or none resolve).
            $transProp => static function (): ?object {
                $shared = (array) config('inertia-i18n.shared', []);

                if ($shared === []) {
                    return null;
                }

                $translations = TranslationLoader::load(app()->getLocale(), ...$shared);

                return $translations === [] ? null : (object) $translations;
            },
        ]);
    }
}
