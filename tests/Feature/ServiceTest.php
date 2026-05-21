<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;

it('can instantiate the service', function () {
    $service = app(TailwindExtractorService::class);

    expect($service)->toBeInstanceOf(TailwindExtractorService::class);
});

it('loads the configuration correctly', function () {
    expect(config('dg-blade-tailwind-extract.class_prefix'))->toBe('TW');
    expect(config('dg-blade-tailwind-extract.hash_length'))->toBe(4);
});
