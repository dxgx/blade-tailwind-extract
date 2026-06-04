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

it('extracts patterns containing group class with group moved outside', function () {
    $testFile = $this->testViewPath . '/test-group.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group rounded-lg bg-white p-4 __">
    <p>Content</p>
</div>
<div class="__wrapper2__ flex items-center text-sm __">
    <span>Valid pattern</span>
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    // One file processed with multiple patterns extracted
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(2);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify the Blade file has group class after the generated class
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('TW-')
        ->and($bladeContent)->toContain('group');
    
    // Verify CSS file contains both patterns without the group class
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('wrapper1')
        ->and($cssContent)->toContain('wrapper2')
        ->and($cssContent)->toContain('@apply rounded-lg bg-white p-4;')
        ->and($cssContent)->not->toContain(' group');
});

it('extracts patterns containing group/ variant with reserved classes moved', function () {
    $testFile = $this->testViewPath . '/test-group-variant.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group/item hover:bg-gray-100 p-2 __">
    <p>Content</p>
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify the Blade file has group/item moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('TW-')
        ->and($bladeContent)->toContain('group/item');
    
    // Verify CSS file contains only extractable classes
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('@apply hover:bg-gray-100 p-2;')
        ->and($cssContent)->not->toContain('group/');
});

it('extracts patterns containing peer class with peer moved outside', function () {
    $testFile = $this->testViewPath . '/test-peer.blade.php';
    $content = <<<'BLADE'
<input type="checkbox" class="__checkbox__ peer sr-only rounded __" />
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify peer class is moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('peer');
    
    // Verify CSS file contains only extractable classes
    $cssContent = File::get($this->testCssPath);
    expect($cssContent)->toContain('@apply sr-only rounded;')
        ->and($cssContent)->not->toContain(' peer');
});

it('extracts patterns containing group-hover variant with reserved classes moved', function () {
    $testFile = $this->testViewPath . '/test-group-hover.blade.php';
    $content = <<<'BLADE'
<div class="__wrapper1__ group-hover:bg-blue-500 rounded-lg p-4 border __">
    <p>Content</p>
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify group-hover is moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('group-hover:bg-blue-500');
    
    // Verify CSS file contains only extractable classes
    expect(File::exists($this->testCssPath))->toBeTrue();
    $cssContent = File::get($this->testCssPath);
    
    // Extract just the CSS rules (without comments)
    preg_match_all('/\.TW-[^\{]+\{([^\}]+)\}/', $cssContent, $matches);
    $rules = implode(' ', $matches[1]);
    
    expect($cssContent)->toContain('@apply rounded-lg p-4 border;')
        ->and($rules)->not->toContain('group-hover');
});

it('extracts patterns containing peer-focus variant with reserved classes moved', function () {
    $testFile = $this->testViewPath . '/test-peer-focus.blade.php';
    $content = <<<'BLADE'
<input type="text" class="peer" />
<div class="__error__ peer-focus:text-red-500 text-sm mt-1 block __">Error message</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify peer-focus is moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('peer-focus:text-red-500');
    
    // Verify CSS file contains only extractable classes
    $cssContent = File::get($this->testCssPath);
    
    // Extract just the CSS rules (without comments)
    preg_match_all('/\.TW-[^\{]+\{([^\}]+)\}/', $cssContent, $matches);
    $rules = implode(' ', $matches[1]);
    
    expect($cssContent)->toContain('@apply text-sm mt-1 block;')
        ->and($rules)->not->toContain('peer-focus');
});

it('extracts patterns containing group-active variant with reserved classes moved', function () {
    $testFile = $this->testViewPath . '/test-group-active.blade.php';
    $content = <<<'BLADE'
<button class="__btn__ group-active:scale-95 bg-blue-500 px-4 py-2 __">
    Click me
</button>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify group-active is moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('group-active:scale-95');
    
    // Verify CSS file contains only extractable classes
    $cssContent = File::get($this->testCssPath);
    
    // Extract just the CSS rules (without comments)
    preg_match_all('/\.TW-[^\{]+\{([^\}]+)\}/', $cssContent, $matches);
    $rules = implode(' ', $matches[1]);
    
    expect($cssContent)->toContain('@apply bg-blue-500 px-4 py-2;')
        ->and($rules)->not->toContain('group-active');
});

it('extracts patterns containing peer-checked variant with reserved classes moved', function () {
    $testFile = $this->testViewPath . '/test-peer-checked.blade.php';
    $content = <<<'BLADE'
<input type="checkbox" class="peer" />
<div class="__indicator__ peer-checked:bg-green-500 bg-gray-200 rounded p-2 __">
    Checkmark
</div>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(1);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify peer-checked is moved outside
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('peer-checked:bg-green-500');
    
    // Verify CSS file contains only extractable classes
    $cssContent = File::get($this->testCssPath);
    
    // Extract just the CSS rules (without comments)
    preg_match_all('/\.TW-[^\{]+\{([^\}]+)\}/', $cssContent, $matches);
    $rules = implode(' ', $matches[1]);
    
    expect($cssContent)->toContain('@apply bg-gray-200 rounded p-2;')
        ->and($rules)->not->toContain('peer-checked');
});

it('extracts multiple reserved class variants in the same file with all moved', function () {
    $testFile = $this->testViewPath . '/test-multiple-variants.blade.php';
    $content = <<<'BLADE'
<div class="__card__ group hover:shadow-lg p-4 rounded __">
    <h2 class="__title__ group-hover:text-blue-500 text-gray-900 text-lg font-bold __">Title</h2>
    <p class="__body__ text-gray-600 leading-relaxed mt-2 __">Valid pattern</p>
</div>
<input type="checkbox" class="peer" />
<label class="__label__ peer-checked:font-bold cursor-pointer text-sm block __">Check me</label>
BLADE;

    File::put($testFile, $content);

    $result = $this->service->extract($testFile, $this->testCssPath);
    
    // One file processed with all patterns extracted
    expect($result['processed'])->toBe(1);
    expect($result['new_rules'])->toBe(4);
    expect($result['skipped_patterns'])->toBeEmpty();
    
    // Verify all reserved classes are in the output
    $bladeContent = File::get($testFile);
    expect($bladeContent)->toContain('group')
        ->and($bladeContent)->toContain('group-hover:text-blue-500')
        ->and($bladeContent)->toContain('peer-checked:font-bold');
    
    // Verify CSS has only extractable classes
    $cssContent = File::get($this->testCssPath);
    
    // Extract just the CSS rules (without comments)
    preg_match_all('/\.TW-[^\{]+\{([^\}]+)\}/', $cssContent, $matches);
    $rules = implode(' ', $matches[1]);
    
    expect($cssContent)->toContain('@apply hover:shadow-lg p-4 rounded;')
        ->and($cssContent)->toContain('@apply text-gray-900 text-lg font-bold;')
        ->and($rules)->not->toContain(' group')
        ->and($rules)->not->toContain('group-hover')
        ->and($rules)->not->toContain('peer-checked');
});

