<?php

namespace Dxgx\BladeTailwindExtract\Tests;

use Dxgx\BladeTailwindExtract\BladeTailwindExtractServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            BladeTailwindExtractServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default configuration
        $app['config']->set('blade-tailwind-extract.class_prefix', 'TW');
        $app['config']->set('blade-tailwind-extract.hash_length', 4);
        $app['config']->set('blade-tailwind-extract.css_output_path', storage_path('testing/tw-extracted.css'));
    }
}
