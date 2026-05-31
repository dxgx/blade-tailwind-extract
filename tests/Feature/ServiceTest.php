<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Use storage_path to avoid main vendor/ directory filtering
    $this->testViewPath = storage_path('app/testing/views/service');
    $this->testCssPath = storage_path('app/testing/css/service-tw.css');
    
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

it('can instantiate the service', function () {
    $service = app(TailwindExtractorService::class);

    expect($service)->toBeInstanceOf(TailwindExtractorService::class);
});

it('loads the configuration correctly', function () {
    expect(config('dg-blade-tailwind-extract.class_prefix'))->toBe('TW');
    expect(config('dg-blade-tailwind-extract.hash_length'))->toBe(4);
});

it('removes restored classes from CSS file after restoration', function () {
    // Create test blade file with marked classes
    $testFile = $this->testViewPath . '/cleanup-test.blade.php';
    $bladeContent = '<div class="__card__ bg-white p-4 rounded __"></div>';
    File::put($testFile, $bladeContent);
    
    // Extract to create CSS rules
    $extractResult = $this->service->extract($testFile, $this->testCssPath);
    expect($extractResult['processed'])->toBe(1);
    expect($extractResult['new_rules'])->toBe(1);
    
    // Verify CSS file has the rule
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('bg-white p-4 rounded');
    $initialCssLineCount = count(explode("\n", trim($cssContent)));
    
    // Restore (inject) the classes back
    $restoreResult = $this->service->inject($testFile, $this->testCssPath);
    expect($restoreResult['processed'])->toBe(1);
    expect($restoreResult['injected'])->toBe(1);
    expect($restoreResult['restored_classes'])->toBeArray();
    expect(count($restoreResult['restored_classes']))->toBe(1);
    
    // Remove restored classes from CSS
    $this->service->removeRestoredClassesFromCss($this->testCssPath, $restoreResult['restored_classes']);
    
    // Verify CSS rules were removed
    $finalCssContent = File::get($this->testCssPath);
    $finalCssLineCount = count(array_filter(explode("\n", trim($finalCssContent))));
    
    // CSS should be significantly smaller (or empty) after cleanup
    expect($finalCssLineCount)->toBeLessThan($initialCssLineCount);
    
    // The specific class should not exist anymore
    foreach ($restoreResult['restored_classes'] as $className) {
        expect($finalCssContent)->not->toContain('.' . $className . ' {');
    }
});
