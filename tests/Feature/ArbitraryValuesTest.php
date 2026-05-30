<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Use storage_path to avoid main vendor/ directory filtering
    $this->testViewPath = storage_path('app/testing/views/arbitrary');
    $this->testCssPath = storage_path('app/testing/css/arbitrary-tw.css');
    
    File::ensureDirectoryExists($this->testViewPath);
    File::ensureDirectoryExists(dirname($this->testCssPath));
    
    // Override ignored_directories to allow test files
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
    
    $this->service = app(TailwindExtractorService::class);
});

afterEach(function () {
    // Clean up test files
    if (File::exists($this->testCssPath)) {
        File::delete($this->testCssPath);
    }
    if (File::isDirectory($this->testViewPath)) {
        File::deleteDirectory($this->testViewPath);
    }
});

it('can extract classes with CSS variable arbitrary values', function () {
    $testFile = $this->testViewPath . '/css-vars.blade.php';
    $content = '<div class="__theme-button__ hover:bg-[var(--secondary-color)] transition-colors duration-200 cursor-pointer __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    // Check CSS file was created with proper @apply rule
    expect(File::exists($this->testCssPath))->toBeTrue();
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('@apply');
    expect($cssContent)->toContain('hover:bg-[var(--secondary-color)]');
    expect($cssContent)->toContain('transition-colors');
    expect($cssContent)->toContain('duration-200');
    expect($cssContent)->toContain('cursor-pointer');
    
    // Check blade file was modified with generated class
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('TW-');
    expect($bladeContent)->toContain('theme-button');
    expect($bladeContent)->not->toContain('__');
});

it('can extract classes with multiple CSS variables', function () {
    $testFile = $this->testViewPath . '/multi-vars.blade.php';
    $content = '<div class="__card__ bg-[var(--card-bg)] text-[var(--text-color)] border-[var(--border-color)] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('bg-[var(--card-bg)]');
    expect($cssContent)->toContain('text-[var(--text-color)]');
    expect($cssContent)->toContain('border-[var(--border-color)]');
});

it('can extract classes with arbitrary color values', function () {
    $testFile = $this->testViewPath . '/arbitrary-colors.blade.php';
    $content = '<div class="__custom__ bg-[#1da1f2] text-[rgb(255,0,0)] border-[hsl(200,50%,50%)] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('bg-[#1da1f2]');
    expect($cssContent)->toContain('text-[rgb(255,0,0)]');
    expect($cssContent)->toContain('border-[hsl(200,50%,50%)]');
});

it('can extract classes with arbitrary size values', function () {
    $testFile = $this->testViewPath . '/arbitrary-sizes.blade.php';
    $content = '<div class="__sizing__ w-[250px] h-[calc(100vh-64px)] max-w-[50rem] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('w-[250px]');
    expect($cssContent)->toContain('h-[calc(100vh-64px)]');
    expect($cssContent)->toContain('max-w-[50rem]');
});

it('can extract classes with modifier prefixes and arbitrary values', function () {
    $testFile = $this->testViewPath . '/modifiers.blade.php';
    $content = '<div class="__hover-state__ hover:bg-[var(--hover-bg)] focus:ring-[var(--focus-ring)] active:scale-[0.98] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('hover:bg-[var(--hover-bg)]');
    expect($cssContent)->toContain('focus:ring-[var(--focus-ring)]');
    expect($cssContent)->toContain('active:scale-[0.98]');
});

it('can extract and restore classes with arbitrary values', function () {
    $testFile = $this->testViewPath . '/restore-test.blade.php';
    $originalContent = '<div class="__button__ hover:bg-[var(--primary)] px-4 py-2 rounded-lg __"></div>';
    
    File::put($testFile, $originalContent);
    
    // Extract
    $extractResult = $this->service->extract($testFile, $this->testCssPath);
    expect($extractResult['processed'])->toBe(1);
    
    $extractedContent = File::get($testFile);
    expect($extractedContent)->not->toContain('__');
    expect($extractedContent)->toContain('TW-');
    
    // Restore
    $restoreResult = $this->service->inject($testFile, $this->testCssPath);
    expect($restoreResult['processed'])->toBe(1);
    expect($restoreResult['injected'])->toBeGreaterThan(0);
    
    $restoredContent = File::get($testFile);
    expect($restoredContent)->toContain('hover:bg-[var(--primary)]');
    expect($restoredContent)->toContain('px-4');
    expect($restoredContent)->toContain('py-2');
    expect($restoredContent)->toContain('rounded-lg');
});

it('can handle complex arbitrary value expressions', function () {
    $testFile = $this->testViewPath . '/complex.blade.php';
    $content = '<div class="__complex__ bg-[linear-gradient(to_right,var(--start),var(--end))] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.1)] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('bg-[linear-gradient(to_right,var(--start),var(--end))]');
    expect($cssContent)->toContain('shadow-[0_10px_15px_-3px_rgba(0,0,0,0.1)]');
});

it('validates that arbitrary values work in @class directive', function () {
    $testFile = $this->testViewPath . '/at-class.blade.php';
    $content = <<<'BLADE'
<div @class([
    '__theme__ hover:bg-[var(--secondary-color)] transition-colors __',
    'active' => true,
])></div>
BLADE;
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('hover:bg-[var(--secondary-color)]');
    
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('TW-');
    expect($bladeContent)->not->toContain('__theme__');
});

it('validates that arbitrary values work in ->class() method', function () {
    $testFile = $this->testViewPath . '/class-method.blade.php';
    $content = <<<'BLADE'
<div {{ $attributes->class([
    '__dynamic__ bg-[var(--card-bg)] p-4 __',
    'shadow-lg' => $elevated,
]) }}></div>
BLADE;
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('bg-[var(--card-bg)]');
    
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('TW-');
    expect($bladeContent)->not->toContain('__dynamic__');
});

it('handles responsive modifiers with arbitrary values', function () {
    $testFile = $this->testViewPath . '/responsive.blade.php';
    $content = '<div class="__responsive__ sm:w-[200px] md:w-[var(--md-width)] lg:w-[calc(100%-2rem)] __"></div>';
    
    File::put($testFile, $content);
    
    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('sm:w-[200px]');
    expect($cssContent)->toContain('md:w-[var(--md-width)]');
    expect($cssContent)->toContain('lg:w-[calc(100%-2rem)]');
});
