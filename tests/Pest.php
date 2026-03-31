<?php

use AlecsCodes\InertiaI18n\InertiaI18nServiceProvider;
use Inertia\Inertia;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)
    ->beforeEach(function () {
        // Ensure Inertia shared state is clean before every test
        Inertia::flushShared();

        $this->app['view']->addLocation(__DIR__.'/views');
    })
    ->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Package providers loaded by Orchestra Testbench
|--------------------------------------------------------------------------
| Define this function so Testbench picks it up automatically.
*/
function getPackageProviders($app): array
{
    return [
        InertiaServiceProvider::class,
        InertiaI18nServiceProvider::class,
    ];
}
