<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Use storage_path to avoid project root security check
    $this->testDir = storage_path('app/testing/views/reserved_moving_test');
    $this->cssFile = storage_path('app/testing/css/reserved-moving-tw.css');
    
    // Clean up test files
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    File::makeDirectory($this->testDir, 0755, true);
    File::ensureDirectoryExists(dirname($this->cssFile));
    
    // Override ignored_directories to allow test files
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
});

afterEach(function () {
    // Clean up
    if (File::exists($this->cssFile)) {
        File::delete($this->cssFile);
    }
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('moves group-hover classes outside wrapper in simple class attribute', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<x-iconsV2.chevron-right class="__nav_link_ico__ size-4 fill-gray-800 group-hover:translate-x-1 transition-transform __"/>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Check the modified blade file
    $modifiedContent = File::get($bladeFile);
    
    // Should have the generated class name and the group-hover class outside
    expect($modifiedContent)->toContain('TW-')
        ->and($modifiedContent)->toContain('group-hover:translate-x-1')
        ->and($modifiedContent)->not->toContain('__nav_link_ico__');
    
    // Check CSS file
    $cssContent = File::get($this->cssFile);
    
    // Should contain @apply with non-reserved classes only
    expect($cssContent)->toContain('@apply size-4 fill-gray-800 transition-transform;')
        ->and($cssContent)->not->toContain('group-hover');
});

it('moves peer-focus classes outside wrapper', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="__card__ bg-white p-4 peer-focus:bg-gray-100 rounded shadow __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('peer-focus:bg-gray-100')
        ->and($modifiedContent)->not->toContain('__card__');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply bg-white p-4 rounded shadow;')
        ->and($cssContent)->not->toContain('peer-focus');
});

it('moves group and peer base classes outside wrapper', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="__container__ flex items-center group space-x-2 peer __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group peer')
        ->and($modifiedContent)->not->toContain('__container__');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply flex items-center space-x-2;')
        ->and($cssContent)->not->toContain('group')
        ->and($cssContent)->not->toContain('peer');
});

it('moves named group variants outside wrapper', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="__sidebar__ text-sm font-medium group/sidebar group-hover/sidebar:bg-blue-50 __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group/sidebar')
        ->and($modifiedContent)->toContain('group-hover/sidebar:bg-blue-50');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply text-sm font-medium;')
        ->and($cssContent)->not->toContain('group/');
});

it('moves multiple reserved classes and preserves order', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="__complex__ text-lg group-hover:text-blue-500 font-bold peer-focus:underline bg-white group __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    
    $modifiedContent = File::get($bladeFile);
    
    // Reserved classes should appear in their original order after the wrapper
    expect($modifiedContent)->toMatch('/TW-[a-f0-9]+-complex[^"]*group-hover:text-blue-500[^"]*peer-focus:underline[^"]*group/');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply text-lg font-bold bg-white;')
        ->and($cssContent)->not->toContain('group-hover')
        ->and($cssContent)->not->toContain('peer-focus')
        ->and($cssContent)->not->toContain(' group');
});

it('wraps classes in wire:class with reserved classes moved', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<button wire:class="__btn__ px-4 py-2 group-hover:bg-blue-600 bg-blue-500 __">Click</button>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group-hover:bg-blue-600')
        ->and($modifiedContent)->not->toContain('__btn__');
});

it('wraps classes in @class directive with reserved classes moved', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div @class(["__card__ bg-white p-4 group-hover:shadow-lg rounded __"])></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(1);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group-hover:shadow-lg');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply bg-white p-4 rounded;')
        ->and($cssContent)->not->toContain('group-hover');
});

it('wraps classes in @class conditional arrays with reserved classes moved', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div @class([
    "__base__ text-base font-normal group-hover:font-bold __",
    "__active__ bg-blue-500 text-white peer-focus:ring __" => $isActive,
])></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(2);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group-hover:font-bold')
        ->and($modifiedContent)->toContain('peer-focus:ring');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply text-base font-normal;')
        ->and($cssContent)->toContain('@apply bg-blue-500 text-white;')
        ->and($cssContent)->not->toContain('group-hover')
        ->and($cssContent)->not->toContain('peer-focus');
});

it('wraps classes in :class ternary with reserved classes moved', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div :class="isOpen ? '__open__ bg-white p-4 group-hover:bg-gray-50 __' : '__closed__ bg-gray-100 p-2 peer-focus:bg-gray-200 __'"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    expect($result['new_rules'])->toBe(2);
    
    $modifiedContent = File::get($bladeFile);
    
    expect($modifiedContent)->toContain('group-hover:bg-gray-50')
        ->and($modifiedContent)->toContain('peer-focus:bg-gray-200');
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply bg-white p-4;')
        ->and($cssContent)->toContain('@apply bg-gray-100 p-2;')
        ->and($cssContent)->not->toContain('group-hover')
        ->and($cssContent)->not->toContain('peer-focus');
});

it('does not extract if only reserved classes remain after separation', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // All classes are reserved
    $content = <<<'BLADE'
<div class="__test__ group group-hover:bg-gray-100 peer peer-focus:ring __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    // Should not extract because only reserved classes
    expect($result['new_rules'])->toBe(0);
    expect($result['skipped_patterns'])->toHaveCount(1);
    expect($result['skipped_patterns'][0]['reason'])->toBe('only contains reserved classes');
    
    $modifiedContent = File::get($bladeFile);
    
    // Should remain unchanged
    expect($modifiedContent)->toBe($content);
});

it('wraps when extractable classes meet minimum threshold', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // With min=3 (default), if we have 3 extractable classes + reserved classes,
    // it should wrap
    $content = <<<'BLADE'
<div class="__test__ p-4 bg-white rounded group-hover:bg-gray-100 peer-focus:ring __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    $result = $extractor->extract($this->testDir, $this->cssFile);
    
    // Should extract because 3 extractable classes (p-4, bg-white, rounded)
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply p-4 bg-white rounded;');
});

it('can restore wrapped classes with moved reserved classes', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="__test__ p-4 bg-white rounded group-hover:bg-gray-100 __"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    $config = config('dg-blade-tailwind-extract');
    $extractor = new TailwindExtractorService($config);
    
    // Extract
    $extractor->extract($this->testDir, $this->cssFile);
    
    // Now restore
    $restored = $extractor->inject($this->testDir, $this->cssFile);
    
    $modifiedContent = File::get($bladeFile);
    
    // Should restore to original (with marker names)
    expect($modifiedContent)->toContain('__test__')
        ->and($modifiedContent)->toContain('p-4 bg-white rounded')
        ->and($modifiedContent)->toContain('group-hover:bg-gray-100')
        ->and($modifiedContent)->not->toContain('TW-');
});
