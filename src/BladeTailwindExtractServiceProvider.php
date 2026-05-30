<?php

namespace Dxgx\BladeTailwindExtract;

use Dxgx\BladeTailwindExtract\Commands\BladeTailwindExtractCommand;
use Dxgx\BladeTailwindExtract\Commands\BladeTailwindRestoreCommand;
use Dxgx\BladeTailwindExtract\Commands\BladeTailwindWrapCommand;
use Illuminate\Support\ServiceProvider;

class BladeTailwindExtractServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dg-blade-tailwind-extract.php',
            'dg-blade-tailwind-extract'
        );

        $this->app->singleton(TailwindExtractorService::class, function ($app) {
            return new TailwindExtractorService($app['config']['dg-blade-tailwind-extract']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dg-blade-tailwind-extract.php' => config_path('dg-blade-tailwind-extract.php'),
            ], 'dg-blade-tailwind-extract-config');

            $this->commands([
                BladeTailwindWrapCommand::class,
                BladeTailwindExtractCommand::class,
                BladeTailwindRestoreCommand::class,
            ]);
        }
    }
}
