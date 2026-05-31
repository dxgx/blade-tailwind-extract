<?php

use Illuminate\Support\Facades\File;
use Dxgx\BladeTailwindExtract\TailwindExtractorService;

beforeEach(function () {
    // Use storage_path to avoid project root security check
    $this->testViewPath = storage_path('app/testing/views/skipped');
    $this->testCssPath = storage_path('app/testing/css/skipped-tw.css');

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

it('skips patterns containing group class and reports them', function () {
    $testFile = $this->testViewPath . '/test-group.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group rounded-lg bg-white __">
    <p>Content</p>
</div>
<div class="__wrapper2__ flex items-center __">
    <span>Valid pattern</span>
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('wrapper1');
    expect($result['skipped_patterns'][0]['reason'])->toContain('group');
    
    // Verify the Blade file still contains the group class inline
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__wrapper1__ group rounded-lg bg-white __');
    expect($bladeContent)->toContain('TW-'); // The valid pattern should be extracted
    
    // Verify CSS file was created and contains only the valid pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('wrapper2');
    expect($cssContent)->not->toContain('wrapper1');
});

it('skips patterns containing group/ variant and reports them', function () {
    $testFile = $this->testViewPath . '/test-group-variant.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group/item hover:bg-gray-100 __">
    <p>Content</p>
</div>
<div class="__wrapper2__ flex items-center text-sm __">
    <span>Valid pattern</span>
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('wrapper1');
    expect($result['skipped_patterns'][0]['reason'])->toContain('group/');
    
    // Verify the Blade file still contains the group/item class inline
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__wrapper1__ group/item hover:bg-gray-100 __');
    expect($bladeContent)->toContain('TW-'); // The valid pattern should be extracted
    
    // Verify CSS file contains only the valid pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('wrapper2');
    expect($cssContent)->not->toContain('wrapper1');
});

it('skips patterns containing peer class and reports them', function () {
    $testFile = $this->testViewPath . '/test-peer.blade.php';
    $content = <<<'BLADE'
<input type="checkbox" class="__checkbox__ peer sr-only __" />
<label class="__label__ cursor-pointer text-gray-700 __">Label</label>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('checkbox');
    expect($result['skipped_patterns'][0]['reason'])->toContain('peer');
    
    // Verify CSS file contains only the label pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('label');
    expect($cssContent)->not->toContain('checkbox');
    
    // Verify the Blade file still contains the peer class inline
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__checkbox__ peer sr-only __');
});
