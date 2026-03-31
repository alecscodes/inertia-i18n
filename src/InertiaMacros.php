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
            $shared = (array) config('inertia-i18n.shared', []);
            $locale = app()->getLocale();
            $merged = array_unique([...$shared, ...$groups]);

            // Lazy closure so translations are only loaded when Inertia renders
            return $this->with($prop, static fn (): array => TranslationLoader::load($locale, ...$merged));
        };
    }
}
