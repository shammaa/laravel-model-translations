<?php

declare(strict_types=1);

namespace Shammaa\LaravelModelTranslations;

use Illuminate\Support\ServiceProvider;

class LaravelModelTranslationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/translations.php',
            'model-translations'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/translations.php' => config_path('model-translations.php'),
            ], 'model-translations-config');
        }
    }
}
