<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
    
    $this->testDir = storage_path('app/testing/views/dynamic_classes');
    $this->cssFile = storage_path('app/testing/css/dynamic-classes-test.css');
    
    File::ensureDirectoryExists($this->testDir);
    File::ensureDirectoryExists(dirname($this->cssFile));
});

afterEach(function () {
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (File::exists($this->cssFile)) {
        File::delete($this->cssFile);
    }
});

it('does not wrap class attributes with blade variable interpolation', function () {
    $testFile = $this->testDir . '/dynamic-component.blade.php';
    
    // Create file with mixed static and dynamic classes
    $originalContent = <<<'BLADE'
<div class="border-2 {{ $borderClass }} {{ $desktopOnly ? $bgClass : "xs:$bgClass" }} border-2 border-gray-300 bg-white">
    <p>Content</p>
</div>
BLADE;
    
    File::put($testFile, $originalContent);
    
    // Run wrap command
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 3,
        '--yy' => true,
    ])->assertSuccessful();
    
    // Read the result
    $wrappedContent = File::get($testFile);
    
    // Should NOT be wrapped - content should remain unchanged
    expect($wrappedContent)->toBe($originalContent);
    
    // Verify no wrapper markers were added
    expect($wrappedContent)->not->toMatch('/__[a-z]+-[a-z]+-\d+__/');
});

it('does not wrap class attributes with any blade syntax', function () {
    $testFile = $this->testDir . '/blade-syntax.blade.php';
    
    $testCases = [
        // Variable interpolation
        '<div class="p-4 {{ $classes }} rounded">Test 1</div>',
        
        // Ternary in interpolation
        '<div class="border-2 {{ $active ? "bg-blue-500" : "bg-gray-500" }} p-4">Test 2</div>',
        
        // Multiple variables
        '<div class="{{ $spacing }} {{ $color }} {{ $rounded }}">Test 3</div>',
        
        // Mixed with many static classes
        '<div class="border-2 border-gray-300 bg-white {{ $borderClass }} p-4 rounded shadow">Test 4</div>',
    ];
    
    $originalContent = implode("\n", $testCases);
    File::put($testFile, $originalContent);
    
    // Run wrap command
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 3,
        '--yy' => true,
    ])->assertSuccessful();
    
    // Read the result
    $wrappedContent = File::get($testFile);
    
    // All cases should remain unchanged
    expect($wrappedContent)->toBe($originalContent);
    expect($wrappedContent)->not->toContain('__');
});

it('does not extract class attributes with blade variable interpolation', function () {
    $testFile = $this->testDir . '/dynamic-extract.blade.php';
    
    // Manually create a file as if it was wrapped (but it shouldn't have been)
    // This tests the extract logic if something slipped through
    $content = <<<'BLADE'
<div class="border-2 {{ $borderClass }} {{ $desktopOnly ? $bgClass : "xs:$bgClass" }} border-2 border-gray-300 bg-white">
    <p>Content</p>
</div>
BLADE;
    
    File::put($testFile, $content);
    
    $service = app(TailwindExtractorService::class);
    
    // Try to extract (should find nothing to extract since no markers)
    $result = $service->extract($testFile, $this->cssFile);
    
    // Should not process or extract anything
    expect($result['new_rules'])->toBe(0);
    
    // CSS file should either not exist or be empty
    if (File::exists($this->cssFile)) {
        $cssContent = File::get($this->cssFile);
        expect($cssContent)->not->toContain('@apply');
    }
    
    // Original file should be unchanged
    $finalContent = File::get($testFile);
    expect($finalContent)->toBe($content);
});

it('wraps only static class attributes and skips dynamic ones in the same file', function () {
    $testFile = $this->testDir . '/mixed-file.blade.php';
    
    $originalContent = <<<'BLADE'
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-4">Static Title</h1>
    <div class="border-2 {{ $borderClass }} bg-white">Dynamic</div>
    <p class="text-gray-600 leading-relaxed">Static paragraph</p>
</div>
BLADE;
    
    File::put($testFile, $originalContent);
    
    // Run wrap command
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 3,
        '--yy' => true,
    ])->assertSuccessful();
    
    $wrappedContent = File::get($testFile);
    
    // Should wrap static class attributes
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__/');
    
    // But the dynamic one should remain unchanged
    expect($wrappedContent)->toContain('class="border-2 {{ $borderClass }} bg-white"');
    
    // Verify specific static classes got wrapped
    expect($wrappedContent)->toContain('__'); // Has some wrappers
    expect($wrappedContent)->not->toContain('__container mx-auto px-4 py-8__'); // Full match not needed
});

it('handles @class directive with pure blade expressions correctly', function () {
    $testFile = $this->testDir . '/at-class-dynamic.blade.php';
    
    // @class directive with static strings and PHP conditionals (not {{ }} interpolation)
    // The static strings should be wrapped since they don't contain {{ }}
    $originalContent = <<<'BLADE'
<div @class([
    'p-4 rounded shadow',
    'bg-blue-500 text-white' => $isPrimary,
    'bg-gray-500 text-black' => !$isPrimary,
])>Content</div>
BLADE;
    
    File::put($testFile, $originalContent);
    
    // Run wrap command with min=2 to trigger wrapping
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 2,
        '--yy' => true,
    ])->assertSuccessful();
    
    $wrappedContent = File::get($testFile);
    
    // @class with conditionals should still get wrapped for the static strings
    // This is expected behavior - static strings in @class arrays ARE wrapped
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__/');
});

it('does not wrap wire:class with blade interpolation', function () {
    $testFile = $this->testDir . '/wire-class-dynamic.blade.php';
    
    $originalContent = <<<'BLADE'
<div wire:class="p-4 rounded {{ $active ? 'bg-blue-500' : 'bg-gray-500' }}">
    Livewire Component
</div>
BLADE;
    
    File::put($testFile, $originalContent);
    
    // Run wrap command
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 2,
        '--yy' => true,
    ])->assertSuccessful();
    
    $wrappedContent = File::get($testFile);
    
    // Should remain unchanged due to {{ }} interpolation
    expect($wrappedContent)->toBe($originalContent);
    expect($wrappedContent)->not->toMatch('/__[a-z]+-[a-z]+-\d+__/');
});
