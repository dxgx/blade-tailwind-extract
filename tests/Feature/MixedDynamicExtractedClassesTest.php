<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
    
    $this->testDir = storage_path('app/testing/views/mixed_dynamic_extracted');
    $this->cssFile = storage_path('app/testing/css/mixed-dynamic-extracted.css');
    
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

it('restores static extracted classes in attributes with dynamic blade interpolation', function () {
    $testFile = $this->testDir . '/mixed-component.blade.php';
    
    // Create file with ALREADY extracted classes (TW-...) mixed with dynamic {{ }}
    // This simulates a file that was previously extracted and now has both
    $extractedContent = <<<'BLADE'
<div class="gr_list_item_{{$name}} TW-242f-gr-lstitm-cont {{ $desktopOnly ? ' TW-242f-gr-lstitm-cont2 ' : ' TW-242f-gr-lstitm-cont-mob ' }}"></div>
BLADE;
    
    File::put($testFile, $extractedContent);
    
    // Create CSS file with the extracted rules
    $cssContent = <<<'CSS'
.TW-242f-gr-lstitm-cont {
    @apply border-adg-green cursor-pointer text-sm px-4 py-2 relative;
}

.TW-242f-gr-lstitm-cont2 {
    @apply bg-white shadow-lg;
}

.TW-242f-gr-lstitm-cont-mob {
    @apply bg-gray-100 text-xs;
}
CSS;
    
    File::put($this->cssFile, $cssContent);
    
    $service = app(TailwindExtractorService::class);
    
    // Execute restore
    $result = $service->inject($testFile, $this->cssFile);
    
    // Read restored content
    $restoredContent = File::get($testFile);
    
    // Verify restoration occurred
    expect($result['injected'])->toBeGreaterThan(0);
    
    // The static TW-242f-gr-lstitm-cont outside {{ }} should be restored
    expect($restoredContent)->toContain('__gr-lstitm-cont__ border-adg-green cursor-pointer text-sm px-4 py-2 relative __');
    
    // The classes inside {{ }} should ALSO be restored
    expect($restoredContent)->toContain('__gr-lstitm-cont2__ bg-white shadow-lg __');
    expect($restoredContent)->toContain('__gr-lstitm-cont-mob__ bg-gray-100 text-xs __');
    
    // The dynamic part should remain
    expect($restoredContent)->toContain('gr_list_item_{{$name}}');
    expect($restoredContent)->toContain('{{ $desktopOnly ?');
    
    // Verify no corruption of dynamic interpolation
    expect($restoredContent)->not->toContain('gr_list_item___gr-lstitm-cont__');
});

it('does not wrap class attributes that are already partially extracted with dynamic parts', function () {
    $testFile = $this->testDir . '/already-extracted.blade.php';
    
    // A file that's already been extracted and has both TW- classes and {{ }}
    $content = <<<'BLADE'
<div class="dynamic-{{$id}} TW-242f-existing bg-blue-500"></div>
BLADE;
    
    File::put($testFile, $content);
    
    // Try to wrap (should skip because it has {{ }})
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $testFile,
        '--min' => 2,
        '--yy' => true,
    ])->assertSuccessful();
    
    $wrappedContent = File::get($testFile);
    
    // Should remain unchanged because of {{ }} presence
    expect($wrappedContent)->toBe($content);
});

it('extracts marked classes even when attribute has blade interpolation', function () {
    $testFile = $this->testDir . '/marked-with-dynamic.blade.php';
    
    // Manually create a wrapped file (though wrap command should skip these)
    // This tests if extract handles the edge case
    $content = <<<'BLADE'
<div class="prefix-{{$var}} __test-classes__ bg-white p-4 rounded __"></div>
BLADE;
    
    File::put($testFile, $content);
    
    $service = app(TailwindExtractorService::class);
    
    // Try to extract
    $result = $service->extract($testFile, $this->cssFile);
    
    // Extract should process the marked section
    expect($result['new_rules'])->toBe(1);
    
    $extractedContent = File::get($testFile);
    
    // The marked section should be extracted
    expect($extractedContent)->toMatch('/TW-[a-f0-9]{4}-test-classes/');
    
    // The dynamic part should remain untouched
    expect($extractedContent)->toContain('prefix-{{$var}}');
});

it('handles complex scenario with multiple dynamic parts and extracted classes', function () {
    $testFile = $this->testDir . '/complex-mixed.blade.php';
    
    // Complex real-world scenario
    $extractedContent = <<<'BLADE'
<div class="component-{{$type}} TW-a1b2-base TW-a1b2-base-variant">
    <span class="{{$active ? 'active' : 'inactive'}} TW-a1b2-text">Label</span>
    <button class="TW-a1b2-btn {{$size}}">Click</button>
</div>
BLADE;
    
    File::put($testFile, $extractedContent);
    
    // Create CSS
    $cssContent = <<<'CSS'
.TW-a1b2-base {
    @apply p-4 rounded;
}

.TW-a1b2-base-variant {
    @apply shadow-lg border;
}

.TW-a1b2-text {
    @apply text-sm font-bold;
}

.TW-a1b2-btn {
    @apply px-4 py-2 bg-blue-500;
}
CSS;
    
    File::put($this->cssFile, $cssContent);
    
    $service = app(TailwindExtractorService::class);
    
    // Restore all
    $result = $service->inject($testFile, $this->cssFile);
    
    expect($result['injected'])->toBe(4); // All 4 TW- classes should be restored
    
    $restoredContent = File::get($testFile);
    
    // Verify all static TW- classes are restored
    expect($restoredContent)->toContain('__base__ p-4 rounded __');
    expect($restoredContent)->toContain('__base-variant__ shadow-lg border __');
    expect($restoredContent)->toContain('__text__ text-sm font-bold __');
    expect($restoredContent)->toContain('__btn__ px-4 py-2 bg-blue-500 __');
    
    // Verify all dynamic parts remain
    expect($restoredContent)->toContain('component-{{$type}}');
    expect($restoredContent)->toContain('{{$active');
    expect($restoredContent)->toContain('{{$size}}');
    
    // Verify no TW- classes remain
    expect($restoredContent)->not->toContain('TW-a1b2');
});

it('correctly restores prefix classes even when mixed with dynamic interpolation', function () {
    $testFile = $this->testDir . '/prefix-with-dynamic.blade.php';
    
    // The exact scenario from the user's example with prefix classes
    $extractedContent = <<<'BLADE'
<div class="gr_list_item_{{$name}} TW-242f-gr-lstitm-cont {{ $desktopOnly ? ' TW-242f-gr-lstitm-cont2 ' : ' TW-242f-gr-lstitm-cont-mob ' }}"></div>
BLADE;
    
    File::put($testFile, $extractedContent);
    
    // CSS with prefix classes (cont, cont2, cont-mob)
    $cssContent = <<<'CSS'
.TW-242f-gr-lstitm-cont {
    @apply border-adg-green cursor-pointer text-sm px-4 py-2 relative;
}

.TW-242f-gr-lstitm-cont2 {
    @apply bg-white shadow-lg;
}

.TW-242f-gr-lstitm-cont-mob {
    @apply bg-gray-100 text-xs;
}
CSS;
    
    File::put($this->cssFile, $cssContent);
    
    $service = app(TailwindExtractorService::class);
    
    // Restore
    $result = $service->inject($testFile, $this->cssFile);
    
    expect($result['injected'])->toBe(3); // All 3 classes
    
    $restoredContent = File::get($testFile);
    
    // Verify correct restoration with NO corruption
    expect($restoredContent)->toContain('__gr-lstitm-cont__ border-adg-green cursor-pointer text-sm px-4 py-2 relative __');
    expect($restoredContent)->toContain('__gr-lstitm-cont2__ bg-white shadow-lg __');
    expect($restoredContent)->toContain('__gr-lstitm-cont-mob__ bg-gray-100 text-xs __');
    
    // Verify no prefix corruption (this was the original bug)
    expect($restoredContent)->not->toContain('__gr-lstitm-cont__ border-adg-green cursor-pointer text-sm px-4 py-2 relative __ 2');
    expect($restoredContent)->not->toContain('__gr-lstitm-cont__ border-adg-green cursor-pointer text-sm px-4 py-2 relative __ -mob');
    
    // Dynamic parts should remain
    expect($restoredContent)->toContain('gr_list_item_{{$name}}');
    expect($restoredContent)->toContain('{{ $desktopOnly ?');
});
