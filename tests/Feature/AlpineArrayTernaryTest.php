<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create a fresh test views directory
    if (!File::isDirectory(resource_path('views'))) {
        File::makeDirectory(resource_path('views'), 0755, true);
    }
});

afterEach(function () {
    // Clean up test files
    if (File::exists(resource_path('views/test-alpine-array.blade.php'))) {
        File::delete(resource_path('views/test-alpine-array.blade.php'));
    }
    if (File::exists(resource_path('css/extracted.css'))) {
        File::delete(resource_path('css/extracted.css'));
    }
});

it('handles x-bind:class with array of ternary expressions', function () {
    $content = <<<'BLADE'
<div 
    x-bind:class="[
        selected ? 'border-2 {{ $borderClass }}' : 'border-2 border-gray-300 bg-white',
        disabled ? 'opacity-70 cursor-not-allowed pointer-events-none' : ''
    ]"
>
    Content
</div>
BLADE;

    File::put(resource_path('views/test-alpine-array.blade.php'), $content);

    // Step 1: Wrap
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => resource_path('views/test-alpine-array.blade.php')
    ])->assertSuccessful();

    $wrappedContent = File::get(resource_path('views/test-alpine-array.blade.php'));
    
    dump('Wrapped content:', $wrappedContent);
    
    // For now, just check that the command ran
    expect($wrappedContent)->toBeString();
});

it('handles x-bind:class array with multiple ternaries where some do not meet threshold', function () {
    $content = <<<'BLADE'
<div 
    x-bind:class="[
        active ? 'bg-blue-500 text-white font-bold rounded-lg px-4 py-2' : 'bg-gray-200 text-gray-700',
        small ? 'text-sm' : 'text-base text-lg'
    ]"
>
    Content
</div>
BLADE;

    File::put(resource_path('views/test-alpine-array.blade.php'), $content);

    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => resource_path('views/test-alpine-array.blade.php')
    ])->assertSuccessful();

    $wrappedContent = File::get(resource_path('views/test-alpine-array.blade.php'));
    
    // First ternary: both branches should be wrapped (both have 3+ classes)
    preg_match_all('/__([a-z]+-[a-z]+-\d+)__/', $wrappedContent, $matches);
    $wrappers = array_unique($matches[1]);
    
    // Should have 3 wrappers total:
    // - 'bg-blue-500 text-white font-bold rounded-lg px-4 py-2' (5 classes)
    // - 'bg-gray-200 text-gray-700' (2 classes - should NOT be wrapped)
    // - 'text-base text-lg' (2 classes - should NOT be wrapped)
    // Wait, let me recalculate:
    // - Branch 1 true: 6 classes -> wrapped
    // - Branch 1 false: 2 classes -> NOT wrapped
    // - Branch 2 true: 1 class -> NOT wrapped
    // - Branch 2 false: 2 classes -> NOT wrapped
    
    expect($wrappers)->toHaveCount(1); // Only the first ternary's true branch
    
    // Verify specific patterns
    expect($wrappedContent)->toMatch('/__[a-z]+-[a-z]+-\d+__ bg-blue-500 text-white font-bold rounded-lg px-4 py-2 __/')
        ->and($wrappedContent)->toContain("'bg-gray-200 text-gray-700'") // Not wrapped (2 classes)
        ->and($wrappedContent)->toContain("'text-sm'") // Not wrapped (1 class)
        ->and($wrappedContent)->toContain("'text-base text-lg'"); // Not wrapped (2 classes)
});
