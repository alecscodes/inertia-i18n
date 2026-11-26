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

            // Lazy: locale and shared groups are read when Inertia renders the props.
            return $this->with($prop, static function () use ($groups): object {
                $shared = (array) config('inertia-i18n.shared', []);

                // Sent as an object so the frontend always receives a JSON map,
                // even for an empty catalog or integer-like JSON keys.
                return (object) TranslationLoader::load(app()->getLocale(), ...$shared, ...$groups);
            });
        };
    }
}
