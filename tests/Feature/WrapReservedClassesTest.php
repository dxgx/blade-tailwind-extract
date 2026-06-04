<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Use storage_path to avoid project root security check
    $this->testDir = storage_path('app/testing/views/wrap_reserved_test');
    
    // Clean up test files
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    File::makeDirectory($this->testDir, 0755, true);
    
    // Override ignored_directories to allow test files
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
});

afterEach(function () {
    // Clean up
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('wrap command moves reserved classes outside markers in simple class attribute', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<x-iconsV2.chevron-right class="size-4 fill-gray-800 group-hover:translate-x-1 transition-transform"/>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $output = Artisan::output();
    
    // Should indicate reserved classes were moved
    expect($output)->toContain('Moved')
        ->and($output)->toContain('reserved class');
    
    $wrappedContent = File::get($bladeFile);
    
    // Should have wrapper with extractable classes inside and reserved classes outside
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+group-hover:translate-x-1/')
        ->and($wrappedContent)->not->toContain('group-hover:translate-x-1 __');
});

it('wrap command moves peer classes outside markers', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="bg-white p-4 peer-focus:bg-gray-100 rounded shadow"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('peer-focus:bg-gray-100')
        ->and($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+peer-focus:bg-gray-100/');
});

it('wrap command moves multiple reserved classes preserving order', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="text-lg group-hover:text-blue-500 font-bold peer-focus:underline bg-white group"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    // All reserved classes should be outside the wrapper in original order
    expect($wrappedContent)
        ->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+group-hover:text-blue-500[^"]*peer-focus:underline[^"]*group"/');
});

it('wrap command moves reserved classes in wire:class attributes', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<button wire:class="px-4 py-2 group-hover:bg-blue-600 bg-blue-500">Click</button>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('group-hover:bg-blue-600')
        ->and($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+group-hover:bg-blue-600/');
});

it('wrap command moves reserved classes in @class directive', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div @class(["bg-white p-4 group-hover:shadow-lg rounded"])></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('group-hover:shadow-lg')
        ->and($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+group-hover:shadow-lg/');
});

it('wrap command moves reserved classes in @class conditional arrays', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div @class([
    "text-base font-normal p-2 group-hover:font-bold",
    "bg-blue-500 text-white rounded peer-focus:ring" => $isActive,
])></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('group-hover:font-bold')
        ->and($wrappedContent)->toContain('peer-focus:ring')
        ->and($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__/'); // At least one wrapper was created
});

it('wrap command moves reserved classes in :class ternary expressions', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div :class="isOpen ? 'bg-white p-4 group-hover:bg-gray-50' : 'bg-gray-100 p-2 peer-focus:bg-gray-200'"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('group-hover:bg-gray-50')
        ->and($wrappedContent)->toContain('peer-focus:bg-gray-200');
});

it('wrap command does not wrap when only reserved classes would remain', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // Only 2 extractable classes (below min threshold of 3)
    $content = <<<'BLADE'
<div class="p-4 bg-white group-hover:bg-gray-100 peer-focus:ring"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    // Should remain unchanged
    expect($wrappedContent)->toBe($content);
});

it('wrap command wraps when extractable classes meet threshold', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // 3 extractable classes (meets min threshold of 3)
    $content = <<<'BLADE'
<div class="p-4 bg-white rounded group-hover:bg-gray-100 peer-focus:ring"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    // Should be wrapped with reserved classes outside
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__/')
        ->and($wrappedContent)->toContain('group-hover:bg-gray-100')
        ->and($wrappedContent)->toContain('peer-focus:ring');
});

it('wrap command shows summary with moved reserved classes count', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="size-4 fill-gray-800 group-hover:translate-x-1 transition-transform"></div>
<span class="text-lg font-bold peer-focus:underline bg-white"></span>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $output = Artisan::output();
    
    // Should show reserved classes were moved
    expect($output)->toContain('Moved')
        ->and($output)->toContain('reserved class')
        ->and($output)->toContain('maintain parent-child selectors');
});

it('wrap command handles named group variants', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    $content = <<<'BLADE'
<div class="text-sm font-medium p-2 group/sidebar group-hover/sidebar:bg-blue-50"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toContain('group/sidebar')
        ->and($wrappedContent)->toContain('group-hover/sidebar:bg-blue-50')
        ->and($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__[^_]+__\s+group\/sidebar[^"]*group-hover\/sidebar:bg-blue-50/');
});

it('wrap command respects --min option when counting extractable classes', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // 2 extractable + 1 reserved
    $content = <<<'BLADE'
<div class="p-4 bg-white group-hover:bg-gray-100"></div>
BLADE;
    
    File::put($bladeFile, $content);
    
    // With --min=2, should wrap
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--min' => 2,
        '--yy' => true,
    ]);
    
    $wrappedContent = File::get($bladeFile);
    
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__/')
        ->and($wrappedContent)->toContain('group-hover:bg-gray-100');
});

it('wrap command deduplicates based on extractable classes only', function () {
    $bladeFile = $this->testDir.'/component.blade.php';
    
    // Same extractable classes but different reserved classes
    $content = <<<'BLADE'
<div class="p-4 bg-white rounded group-hover:bg-gray-100"></div>
<span class="p-4 bg-white rounded peer-focus:ring"></span>
BLADE;
    
    File::put($bladeFile, $content);
    
    Artisan::call('dg:blade-tailwind:wrap', [
        'target' => $bladeFile,
        '--yy' => true,
    ]);
    
    $output = Artisan::output();
    
    // Should use the same wrapper name since extractable classes are identical
    expect($output)->toContain('2 occurrence');
    
    $wrappedContent = File::get($bladeFile);
    
    // Both should have the same wrapper name
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $wrappedContent, $matches);
    expect($matches[1][0])->toBe($matches[1][1]);
});
