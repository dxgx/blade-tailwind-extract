<?php

namespace Dxgx\BladeTailwindExtract;

use Dxgx\BladeTailwindExtract\Commands\BladeTailwindExtractCommand;
use Illuminate\Support\ServiceProvider;

class BladeTailwindExtractServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blade-tailwind-extract.php',
            'blade-tailwind-extract'
        );

        $this->app->singleton(TailwindExtractorService::class, function ($app) {
            return new TailwindExtractorService($app['config']['blade-tailwind-extract']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/blade-tailwind-extract.php' => config_path('blade-tailwind-extract.php'),
            ], 'blade-tailwind-extract-config');

            $this->commands([
                BladeTailwindExtractCommand::class,
            ]);
        }
    }
}
