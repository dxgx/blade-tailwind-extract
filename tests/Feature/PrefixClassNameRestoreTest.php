<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Override ignored_directories to allow test files
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
});

it('correctly restores class names when one is a prefix of another', function () {
    $testDir = storage_path('app/testing/views/prefix_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/prefix-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file with two similar class names
    $bladeContent = <<<'BLADE'
<div class="TW-242f-gr-lstitm-wrp">First</div>
<div class="TW-242f-gr-lstitm-wrp-mob">Second</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file with both rules
    $cssContent = <<<'CSS'
.TW-242f-gr-lstitm-wrp {
    @apply bg-red-500 text-white;
}

.TW-242f-gr-lstitm-wrp-mob {
    @apply bg-blue-500 text-black;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);

    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions
    expect($result['injected'])->toBe(2);
    
    // The shorter class name should be restored correctly
    expect($restoredContent)->toContain('__gr-lstitm-wrp__ bg-red-500 text-white __');
    
    // The longer class name should NOT be corrupted
    expect($restoredContent)->toContain('__gr-lstitm-wrp-mob__ bg-blue-500 text-black __');
    
    // Make sure the corruption doesn't occur
    expect($restoredContent)->not->toContain('__gr-lstitm-wrp__ bg-red-500 text-white __ -mob');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});

it('correctly handles multiple levels of prefix class names', function () {
    $testDir = storage_path('app/testing/views/multi_prefix_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/multi-prefix-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file with three levels of prefixes
    $bladeContent = <<<'BLADE'
<div class="TW-242f-btn">Short</div>
<div class="TW-242f-btn-lg">Medium</div>
<div class="TW-242f-btn-lg-primary">Long</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file with all three rules
    $cssContent = <<<'CSS'
.TW-242f-btn {
    @apply px-4 py-2;
}

.TW-242f-btn-lg {
    @apply px-6 py-3;
}

.TW-242f-btn-lg-primary {
    @apply px-8 py-4 bg-blue-500;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);
    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions
    expect($result['injected'])->toBe(3);
    
    // Each class should be restored correctly without corruption
    expect($restoredContent)->toContain('__btn__ px-4 py-2 __');
    expect($restoredContent)->toContain('__btn-lg__ px-6 py-3 __');
    expect($restoredContent)->toContain('__btn-lg-primary__ px-8 py-4 bg-blue-500 __');
    
    // Make sure no corruption occurred
    expect($restoredContent)->not->toContain('__btn__ px-4 py-2 __ -lg');
    expect($restoredContent)->not->toContain('__btn-lg__ px-6 py-3 __ -primary');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});

it('handles prefix class names in @class directive', function () {
    $testDir = storage_path('app/testing/views/prefix_at_class_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/prefix-at-class-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file with @class directive
    $bladeContent = <<<'BLADE'
<div @class([
    'TW-242f-card' => true,
    'TW-242f-card-hover' => $isHoverable,
])>Content</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file with both rules
    $cssContent = <<<'CSS'
.TW-242f-card {
    @apply rounded shadow;
}

.TW-242f-card-hover {
    @apply hover:shadow-lg;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);
    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions
    expect($result['injected'])->toBe(2);
    expect($restoredContent)->toContain('__card__ rounded shadow __');
    expect($restoredContent)->toContain('__card-hover__ hover:shadow-lg __');
    
    // Make sure no corruption
    expect($restoredContent)->not->toContain('__card__ rounded shadow __ -hover');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});

it('handles prefix class names in ->class() method', function () {
    $testDir = storage_path('app/testing/views/prefix_method_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/prefix-method-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file with ->class() method
    $bladeContent = <<<'BLADE'
<div {{ $attributes->class([
    'TW-242f-alert',
    'TW-242f-alert-danger' => $type === 'danger',
]) }}>Alert</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file with both rules
    $cssContent = <<<'CSS'
.TW-242f-alert {
    @apply p-4 border;
}

.TW-242f-alert-danger {
    @apply border-red-500 bg-red-100;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);
    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions
    expect($result['injected'])->toBe(2);
    expect($restoredContent)->toContain('__alert__ p-4 border __');
    expect($restoredContent)->toContain('__alert-danger__ border-red-500 bg-red-100 __');
    
    // Make sure no corruption
    expect($restoredContent)->not->toContain('__alert__ p-4 border __ -danger');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});

it('handles prefix class names with mixed order in CSS file', function () {
    $testDir = storage_path('app/testing/views/prefix_mixed_order_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/prefix-mixed-order-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file
    $bladeContent = <<<'BLADE'
<div class="TW-242f-a">First</div>
<div class="TW-242f-a-b">Second</div>
<div class="TW-242f-a-b-c">Third</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file with rules in RANDOM order (not sorted)
    $cssContent = <<<'CSS'
.TW-242f-a-b {
    @apply text-lg;
}

.TW-242f-a-b-c {
    @apply text-xl font-bold;
}

.TW-242f-a {
    @apply text-base;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);
    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions - should work regardless of CSS file order
    expect($result['injected'])->toBe(3);
    expect($restoredContent)->toContain('__a__ text-base __');
    expect($restoredContent)->toContain('__a-b__ text-lg __');
    expect($restoredContent)->toContain('__a-b-c__ text-xl font-bold __');
    
    // No corruption
    expect($restoredContent)->not->toContain('-b">');
    expect($restoredContent)->not->toContain('-c">');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});

it('handles multiple occurrences of prefix class names in same file', function () {
    $testDir = storage_path('app/testing/views/prefix_multiple_test');
    $testFile = $testDir . '/component.blade.php';
    $cssFile = storage_path('app/testing/css/prefix-multiple-test-tw.css');

    // Setup
    File::ensureDirectoryExists($testDir);
    File::ensureDirectoryExists(dirname($cssFile));

    // Create Blade file with multiple occurrences
    $bladeContent = <<<'BLADE'
<div class="TW-242f-box">Box 1</div>
<div class="TW-242f-box-shadow">Box 2</div>
<div class="TW-242f-box">Box 3</div>
<div class="TW-242f-box-shadow">Box 4</div>
BLADE;
    file_put_contents($testFile, $bladeContent);

    // Create CSS file
    $cssContent = <<<'CSS'
.TW-242f-box {
    @apply p-4;
}

.TW-242f-box-shadow {
    @apply p-4 shadow-lg;
}
CSS;
    file_put_contents($cssFile, $cssContent);

    // Execute restore
    $service = app(TailwindExtractorService::class);
    $result = $service->inject($testFile, $cssFile);

    // Read the restored content
    $restoredContent = file_get_contents($testFile);

    // Assertions - should handle all 4 occurrences (2 of each)
    expect($result['injected'])->toBe(4);
    
    // Count occurrences
    $boxCount = substr_count($restoredContent, '__box__ p-4 __');
    $boxShadowCount = substr_count($restoredContent, '__box-shadow__ p-4 shadow-lg __');
    
    expect($boxCount)->toBe(2);
    expect($boxShadowCount)->toBe(2);
    
    // No corruption
    expect($restoredContent)->not->toContain('__box__ p-4 __ -shadow');

    // Cleanup
    File::delete($testFile);
    File::delete($cssFile);
    File::deleteDirectory($testDir);
});
