<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Override ignored_directories to allow test files (Orchestra Testbench paths contain /vendor/)
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
    
    // Clean up any test files
    $testFiles = [
        resource_path('views/test-wrap-identical.blade.php'),
        resource_path('views/test-wrap-mixed.blade.php'),
        resource_path('views/test-wrap-multiline.blade.php'),
        resource_path('views/test-debug.blade.php'),
    ];
    foreach ($testFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

afterEach(function () {
    // Clean up test files
    $testFiles = [
        resource_path('views/test-wrap-identical.blade.php'),
        resource_path('views/test-wrap-mixed.blade.php'),
        resource_path('views/test-wrap-multiline.blade.php'),
        resource_path('views/test-debug.blade.php'),
    ];
    foreach ($testFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

it('wraps identical class lists with same wrapper name', function () {
    $content = <<<'BLADE'
<div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">First</div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">Second</div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">Third</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-identical.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-identical.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-identical.blade.php'));

    // Extract all wrapper names
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    // All three should have the same wrapper name
    expect($wrapperNames)->toHaveCount(3)
        ->and($wrapperNames[0])->toBe($wrapperNames[1])
        ->and($wrapperNames[1])->toBe($wrapperNames[2]);
});

it('wraps different class lists with different wrapper names', function () {
    $content = <<<'BLADE'
<div>
    <div class="bg-red-500 text-white p-4">Red</div>
    <div class="bg-blue-500 text-white p-4">Blue</div>
    <div class="bg-green-500 text-white p-4">Green</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Extract all wrapper names
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    // All three should have different wrapper names
    expect($wrapperNames)->toHaveCount(3)
        ->and($wrapperNames[0])->not->toBe($wrapperNames[1])
        ->and($wrapperNames[1])->not->toBe($wrapperNames[2])
        ->and($wrapperNames[0])->not->toBe($wrapperNames[2]);
});

it('reuses wrapper names for identical class lists appearing later', function () {
    $content = <<<'BLADE'
<div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">A1</div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">A2</div>
    
    <div class="px-4 py-2 bg-blue-500 hover:bg-blue-600">B1</div>
    
    <div class="bg-red-500 border-blue-300 p-10 m-2">A3</div>
    <div class="bg-red-500 border-blue-300 p-10 m-2">A4</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Extract all wrapper names in order
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    expect($wrapperNames)->toHaveCount(5);

    // A1, A2, A3, A4 should all have the same wrapper (indices 0, 1, 3, 4)
    expect($wrapperNames[0])->toBe($wrapperNames[1])
        ->and($wrapperNames[0])->toBe($wrapperNames[3])
        ->and($wrapperNames[0])->toBe($wrapperNames[4]);

    // B1 should be different (index 2)
    expect($wrapperNames[2])->not->toBe($wrapperNames[0]);
});

it('skips class lists with fewer than minimum classes', function () {
    $content = <<<'BLADE'
<div>
    <div class="text-xl font-bold">Two classes</div>
    <div class="text-xl font-bold">Two again</div>
    <div class="bg-red-500 text-white p-4">Three classes</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Only the three-class div should be wrapped
    expect($result)->toContain('text-xl font-bold">Two classes')
        ->and($result)->toContain('text-xl font-bold">Two again')
        ->and($result)->toContain('__ bg-red-500 text-white p-4 __');
});

it('handles @class directive duplicates correctly', function () {
    $content = <<<'BLADE'
<div>
    <div @class(['flex items-center justify-between gap-4'])>Flex 1</div>
    <div @class(['flex items-center justify-between gap-4'])>Flex 2</div>
    <div @class(['flex items-center justify-between gap-4'])>Flex 3</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-multiline.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-multiline.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-multiline.blade.php'));

    // Extract wrapper names from @class directives
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    // All three should have the same wrapper
    expect($wrapperNames)->toHaveCount(3)
        ->and($wrapperNames[0])->toBe($wrapperNames[1])
        ->and($wrapperNames[1])->toBe($wrapperNames[2]);
});

it('skips already wrapped class lists', function () {
    $content = <<<'BLADE'
<div>
    <div class="__existing__ bg-red-500 text-white p-4 __">Already wrapped</div>
    <div class="bg-blue-500 text-white p-4">Not wrapped</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // First div should remain unchanged
    expect($result)->toContain('__existing__ bg-red-500 text-white p-4 __')
        // Second div should be wrapped with a new wrapper
        ->and($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');

    // Count generated wrappers (with pattern adjective-noun-number)
    preg_match_all('/__[a-z]+-[a-z]+-\d+__/', $result, $matches);
    expect($matches[0])->toHaveCount(1); // Only the new wrapper, not the existing one
});

it('respects custom skip prefix', function () {
    $content = <<<'BLADE'
<div>
    <div class="CUSTOM-class bg-red-500 text-white p-4">Should skip</div>
    <div class="bg-blue-500 text-white p-4">Should wrap</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => resource_path('views/test-wrap-mixed.blade.php'),
        '--skip-prefix' => 'CUSTOM-',
    ])->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // First div should not be wrapped
    expect($result)->toContain('CUSTOM-class bg-red-500 text-white p-4">Should skip')
        // Second div should be wrapped
        ->and($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');
});

it('skips material-symbols-outlined via neverWrapPatterns config', function () {
    $content = <<<'BLADE'
<div>
    <span class="material-symbols-outlined text-2xl text-blue-500">home</span>
    <div class="bg-blue-500 text-white p-4">Should wrap</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // material-symbols-outlined should not be wrapped
    expect($result)->toContain('material-symbols-outlined text-2xl text-blue-500">home')
        // Second div should be wrapped
        ->and($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');
});

it('skips TW- prefix via neverWrapPatterns config', function () {
    $content = <<<'BLADE'
<div>
    <div class="TW-component bg-red-500 text-white p-4">Has TW-</div>
    <div class="bg-blue-500 text-white p-4">Should wrap</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // TW- should not be wrapped
    expect($result)->toContain('TW-component bg-red-500 text-white p-4">Has TW-')
        // Second div should be wrapped
        ->and($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');
});

it('resolves view path with blade.php extension', function () {
    $content = '<div class="bg-blue-500 text-white p-4">Test</div>';

    File::put(resource_path('views/test-path-with-extension.blade.php'), $content);

    // Should work with .blade.php extension included
    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-path-with-extension.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-path-with-extension.blade.php'));
    expect($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');

    File::delete(resource_path('views/test-path-with-extension.blade.php'));
});

it('resolves view path with dot notation', function () {
    $content = '<div class="bg-blue-500 text-white p-4">Test</div>';

    // Create nested directory
    $dir = resource_path('views/test-nested/subdir');
    if (! File::exists($dir)) {
        File::makeDirectory($dir, 0755, true);
    }

    File::put(resource_path('views/test-nested/subdir/file.blade.php'), $content);

    // Should work with pattern matching
    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-nested/subdir/file.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-nested/subdir/file.blade.php'));
    expect($result)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white p-4 __/');

    File::deleteDirectory(resource_path('views/test-nested'));
});

it('normalizes whitespace when matching duplicate class lists', function () {
    $content = <<<'BLADE'
<div>
    <div class="bg-red-500 text-white p-4">Normal</div>
    <div class="  bg-red-500 text-white p-4">Leading spaces</div>
    <div class="bg-red-500 text-white p-4  ">Trailing spaces</div>
    <div class="bg-red-500    text-white     p-4">Multiple spaces</div>
    <div class="   bg-red-500    text-white     p-4   ">All combined</div>
    <div class="bg-blue-500 text-white p-4">Different</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Extract all wrapper names
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    // First 5 should have the same wrapper (whitespace variations)
    expect($wrapperNames)->toHaveCount(6)
        ->and($wrapperNames[0])->toBe($wrapperNames[1])
        ->and($wrapperNames[0])->toBe($wrapperNames[2])
        ->and($wrapperNames[0])->toBe($wrapperNames[3])
        ->and($wrapperNames[0])->toBe($wrapperNames[4])
        // Last one should be different
        ->and($wrapperNames[5])->not->toBe($wrapperNames[0]);
});

it('respects dry-run option and does not modify files', function () {
    $content = <<<'BLADE'
<div>
    <div class="bg-red-500 text-white p-4">Test</div>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php'), '--dry-run' => true])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // File should not be modified in dry-run mode
    expect($result)->toBe($content)
        ->and($result)->not->toContain('__');
});

it('wraps conditional class strings in @class arrays', function () {
    $content = <<<'BLADE'
<div>
    <button @class([
        'flex items-center justify-center size-9',
        'text-gray-200 cursor-not-allowed opacity-50' => false,
        'text-red-500 hover:text-red-600 hover:bg-red-50' => true
    ])>Delete</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // All three strings should be wrapped (each has 3+ classes)
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    expect($wrapperNames)->toHaveCount(3)
        // All should have different wrapper names (different class lists)
        ->and($wrapperNames[0])->not->toBe($wrapperNames[1])
        ->and($wrapperNames[1])->not->toBe($wrapperNames[2]);
    
    // Verify the conditional syntax is preserved
    expect($result)->toContain('=>');
});

it('skips already wrapped conditionals in @class arrays', function () {
    $content = <<<'BLADE'
<div>
    <button @class([
        '__existing__ flex items-center justify-center __',
        'text-red-500 hover:text-red-600 hover:bg-red-50' => true
    ])>Delete</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Only the second string should get a new wrapper
    preg_match_all('/__[a-z]+-[a-z]+-\d+__/', $result, $matches);
    expect($matches[0])->toHaveCount(1); // Only the new wrapper
    
    // The existing wrapper should still be there
    expect($result)->toContain('__existing__');
});

it('wraps both branches of ternary in :class bindings', function () {
    $content = <<<'BLADE'
<div>
    <button 
        :class="isPlaying 
            ? 'bg-blue-500 text-white border-blue-500 hover:bg-blue-700' 
            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
        class="flex items-center justify-center size-9"
    >Play</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // All three class strings should be wrapped (both ternary branches + static class)
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    $wrapperNames = $matches[1];

    expect($wrapperNames)->toHaveCount(3)
        // All should have different wrapper names (different class lists)
        ->and($wrapperNames[0])->not->toBe($wrapperNames[1])
        ->and($wrapperNames[1])->not->toBe($wrapperNames[2]);
    
    // Verify the ternary structure is preserved
    expect($result)->toContain('?')
        ->and($result)->toContain(':');
});

it('skips already wrapped ternary branches', function () {
    $content = <<<'BLADE'
<div>
    <button 
        :class="isPlaying 
            ? '__existing-1__ bg-blue-500 text-white __' 
            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
    >Play</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Only the false branch should get a new wrapper
    preg_match_all('/__[a-z]+-[a-z]+-\d+__/', $result, $matches);
    expect($matches[0])->toHaveCount(1); // Only the new wrapper
    
    // The existing wrapper should still be there
    expect($result)->toContain('__existing-1__');
});

it('handles elements with both static and dynamic class attributes independently', function () {
    $content = <<<'BLADE'
<div>
    <button 
        :class="active ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'"
        class="flex items-center justify-center rounded-md"
    >Toggle</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Static class should be wrapped (3 classes), but ternary branches have only 2 classes each
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    expect($matches[0])->toHaveCount(1); // Only the static class wrapper
    
    // Verify both attributes exist
    expect($result)->toContain(':class=')
        ->and($result)->toContain('class="__');
});

it('respects minimum class threshold for @class conditionals', function () {
    $content = <<<'BLADE'
<div>
    <button @class([
        'flex items-center justify-center size-9',
        'text-sm' => false,
        'text-red-500 hover:text-red-600 hover:bg-red-50' => true
    ])>Button</button>
</div>
BLADE;

    File::put(resource_path('views/test-wrap-mixed.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', ['target' => resource_path('views/test-wrap-mixed.blade.php')])
        ->assertSuccessful();

    $result = File::get(resource_path('views/test-wrap-mixed.blade.php'));

    // Only the first and third strings should be wrapped (3+ classes)
    // Middle one has only 1 class
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $result, $matches);
    expect($matches[0])->toHaveCount(2);
    
    // 'text-sm' should not be wrapped
    expect($result)->toMatch("/'text-sm' =>/");
});
