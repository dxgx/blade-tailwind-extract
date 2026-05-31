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

it('skips patterns containing group-hover variant and reports them', function () {
    $testFile = $this->testViewPath . '/test-group-hover.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group-hover:bg-blue-500 rounded-lg __">
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
    expect($result['skipped_patterns'][0]['reason'])->toContain('group-*:');
    
    // Verify the Blade file still contains the group-hover class inline
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__wrapper1__ group-hover:bg-blue-500 rounded-lg __');
    expect($bladeContent)->toContain('TW-'); // The valid pattern should be extracted
    
    // Verify CSS file contains only the valid pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('wrapper2');
    expect($cssContent)->not->toContain('wrapper1');
});

it('skips patterns containing peer-focus variant and reports them', function () {
    $testFile = $this->testViewPath . '/test-peer-focus.blade.php';
    $content = <<<'BLADE'
<input type="text" class="peer" />
<div class="__error__ peer-focus:text-red-500 text-sm __">Error message</div>
<div class="__label__ text-gray-700 font-medium __">Valid label</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('error');
    expect($result['skipped_patterns'][0]['reason'])->toContain('peer-*:');
    
    // Verify CSS file contains only the label pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('label');
    expect($cssContent)->not->toContain('error');
    
    // Verify the Blade file still contains the peer-focus class inline
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__error__ peer-focus:text-red-500 text-sm __');
});

it('skips patterns containing group-active variant and reports them', function () {
    $testFile = $this->testViewPath . '/test-group-active.blade.php';
    $content = <<<'BLADE'
<button class="__btn__ group-active:scale-95 bg-blue-500 __">
    Click me
</button>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(0);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('btn');
    expect($result['skipped_patterns'][0]['reason'])->toContain('group-*:');
    
    // Verify the Blade file still contains the original classes
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('__btn__ group-active:scale-95 bg-blue-500 __');
});

it('skips patterns containing peer-checked variant and reports them', function () {
    $testFile = $this->testViewPath . '/test-peer-checked.blade.php';
    $content = <<<'BLADE'
<input type="checkbox" class="peer" />
<div class="__indicator__ peer-checked:bg-green-500 bg-gray-200 __">
    Checkmark
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(0);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(1);
    expect($result['skipped_patterns'][0]['name'])->toBe('indicator');
    expect($result['skipped_patterns'][0]['reason'])->toContain('peer-*:');
});

it('skips multiple reserved class variants in the same file', function () {
    $testFile = $this->testViewPath . '/test-multiple-variants.blade.php';
    $content = <<<'BLADE'
<div class="__card__ group hover:shadow-lg __">
    <h2 class="__title__ group-hover:text-blue-500 text-gray-900 __">Title</h2>
    <p class="__body__ text-gray-600 leading-relaxed __">Valid pattern</p>
</div>
<input type="checkbox" class="peer" />
<label class="__label__ peer-checked:font-bold cursor-pointer __">Check me</label>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeArray();
    expect(count($result['skipped_patterns']))->toBe(3);
    
    // Verify all three patterns were skipped
    $skippedNames = array_column($result['skipped_patterns'], 'name');
    expect($skippedNames)->toContain('card');
    expect($skippedNames)->toContain('title');
    expect($skippedNames)->toContain('label');
    
    // Verify CSS file contains only the valid body pattern
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('body');
    expect($cssContent)->not->toContain('card');
    expect($cssContent)->not->toContain('title');
    expect($cssContent)->not->toContain('label');
});
