<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;

it('can instantiate the service', function () {
    $service = app(TailwindExtractorService::class);

    expect($service)->toBeInstanceOf(TailwindExtractorService::class);
});

it('loads the configuration correctly', function () {
    expect(config('blade-tailwind-extract.class_prefix'))->toBe('TW');
    expect(config('blade-tailwind-extract.hash_length'))->toBe(4);
});
