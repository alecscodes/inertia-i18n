<?php

namespace AlecsCodes\InertiaI18n\Tests;

use AlecsCodes\InertiaI18n\InertiaI18nServiceProvider;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, InertiaI18nServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['view']->addLocation(__DIR__.'/views');
    }
}
