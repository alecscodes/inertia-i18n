<?php

namespace AlecsCodes\InertiaI18n\Tests;

final class CustomPropsTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('inertia-i18n.props.locale', 'current_locale');
        $app['config']->set('inertia-i18n.props.translations', 'i18n');
        $app['config']->set('inertia-i18n.props.translation_locales', 'i18nLocales');
    }
}
