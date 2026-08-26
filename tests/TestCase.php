<?php

declare(strict_types=1);

namespace Shammaa\LaravelModelTranslations\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shammaa\LaravelModelTranslations\LaravelModelTranslationsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelModelTranslationsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
