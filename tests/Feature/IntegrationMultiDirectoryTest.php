<?php

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['dg-blade-tailwind-extract.ignored_directories' => []]);
    
    $this->testRoot = storage_path('app/testing/integration/multi_dir');
    $this->cssFile = storage_path('app/testing/css/integration-multi-dir.css');
    
    // Clean up from previous runs
    if (File::isDirectory($this->testRoot)) {
        File::deleteDirectory($this->testRoot);
    }
    if (File::exists($this->cssFile)) {
        File::delete($this->cssFile);
    }
    
    File::ensureDirectoryExists(dirname($this->cssFile));
});

afterEach(function () {
    if (File::isDirectory($this->testRoot)) {
        File::deleteDirectory($this->testRoot);
    }
    if (File::exists($this->cssFile)) {
        File::delete($this->cssFile);
    }
});

it('handles wrap, extract, and restore on multiple directories with multiple files', function () {
    // Create directory structure
    $dirs = [
        $this->testRoot . '/components',
        $this->testRoot . '/layouts',
        $this->testRoot . '/pages',
    ];
    
    foreach ($dirs as $dir) {
        File::ensureDirectoryExists($dir);
    }
    
    // Create test files with various patterns
    // File 1: components/card.blade.php (class attributes)
    $file1 = $dirs[0] . '/card.blade.php';
    File::put($file1, <<<'BLADE'
<div class="bg-white p-4 rounded shadow">
    <h2 class="text-xl font-bold mb-2">Title</h2>
    <p class="text-gray-600">Content</p>
</div>
BLADE
    );
    
    // File 2: components/button.blade.php (@class directive)
    $file2 = $dirs[0] . '/button.blade.php';
    File::put($file2, <<<'BLADE'
<button @class([
    'px-4 py-2 rounded' => true,
    'bg-blue-500 text-white' => $primary,
])>Click</button>
BLADE
    );
    
    // File 3: layouts/header.blade.php (->class method + duplicate from file1)
    $file3 = $dirs[1] . '/header.blade.php';
    File::put($file3, <<<'BLADE'
<header {{ $attributes->class([
    'bg-gray-800 text-white p-6',
]) }}>
    <div class="bg-white p-4 rounded shadow">Logo</div>
</header>
BLADE
    );
    
    // File 4: layouts/footer.blade.php (:class ternary)
    $file4 = $dirs[1] . '/footer.blade.php';
    File::put($file4, <<<'BLADE'
<footer :class="isSticky ? 'fixed bottom-0 w-full' : 'relative mt-8'">
    <div class="text-center p-4">Footer</div>
</footer>
BLADE
    );
    
    // File 5: pages/home.blade.php (mix of patterns + duplicate)
    $file5 = $dirs[2] . '/home.blade.php';
    File::put($file5, <<<'BLADE'
<div class="container mx-auto px-4">
    <h1 class="text-xl font-bold mb-2">Home</h1>
    <div @class([
        'grid grid-cols-3 gap-4' => true,
    ])>Content</div>
</div>
BLADE
    );
    
    // File 6: pages/about.blade.php (static classes)
    $file6 = $dirs[2] . '/about.blade.php';
    File::put($file6, <<<'BLADE'
<div class="container mx-auto px-4">
    <article class="prose lg:prose-xl">About content</article>
</div>
BLADE
    );
    
    $service = app(TailwindExtractorService::class);
    
    // STEP 1: Wrap - mark classes for extraction
    $this->artisan('dg:blade-tailwind:wrap', [
        'target' => $this->testRoot,
        '--min' => 3,
        '--yy' => true,
    ])->assertSuccessful();
    
    // Verify wrapping occurred
    $file1Content = File::get($file1);
    expect($file1Content)->toContain('__');
    expect($file1Content)->toMatch('/__[a-z]+-[a-z]+-\d+__/'); // wrapper format
    
    // STEP 2: Extract - convert to CSS
    $extractResult = $service->extract($this->testRoot, $this->cssFile);
    
    expect($extractResult['processed'])->toBeGreaterThan(0);
    expect($extractResult['new_rules'])->toBeGreaterThan(0);
    expect(File::exists($this->cssFile))->toBeTrue();
    
    // Verify CSS file has proper rules
    $cssContent = File::get($this->cssFile);
    expect($cssContent)->toContain('@apply');
    expect($cssContent)->toMatch('/\.TW-[a-f0-9]{4}-/'); // class format
    
    // Verify Blade files have generated class names
    $file1Extracted = File::get($file1);
    expect($file1Extracted)->toMatch('/TW-[a-f0-9]{4}-/');
    expect($file1Extracted)->not->toContain('bg-white p-4 rounded shadow'); // should be extracted
    
    // Store extracted state for later comparison
    $extractedFiles = [
        $file1 => File::get($file1),
        $file2 => File::get($file2),
        $file3 => File::get($file3),
        $file4 => File::get($file4),
        $file5 => File::get($file5),
        $file6 => File::get($file6),
    ];
    
    // STEP 3: Restore - convert back to inline classes
    $restoreResult = $service->inject($this->testRoot, $this->cssFile);
    
    expect($restoreResult['processed'])->toBeGreaterThan(0);
    expect($restoreResult['injected'])->toBeGreaterThan(0);
    
    // Verify restoration occurred
    $file1Restored = File::get($file1);
    expect($file1Restored)->toContain('__');
    expect($file1Restored)->toContain('bg-white');
    expect($file1Restored)->not->toContain('TW-'); // should be restored
    
    // Verify all files are restored
    foreach ([$file1, $file2, $file3, $file4, $file5, $file6] as $file) {
        $content = File::get($file);
        expect($content)->not->toContain('TW-');
    }
});

it('generates consistent file hashes for individual vs bulk extraction', function () {
    // Create test directory
    $testDir = $this->testRoot . '/hash_test';
    File::ensureDirectoryExists($testDir);
    
    // Create two test files in the same directory
    $file1 = $testDir . '/component1.blade.php';
    $file2 = $testDir . '/component2.blade.php';
    
    File::put($file1, <<<'BLADE'
<div class="__test-one__ bg-red-500 text-white p-4 __">File 1</div>
BLADE
    );
    
    File::put($file2, <<<'BLADE'
<div class="__test-two__ bg-blue-500 text-black p-4 __">File 2</div>
BLADE
    );
    
    $service = app(TailwindExtractorService::class);
    
    // BULK EXTRACTION: Extract both files at once
    $bulkCssFile = storage_path('app/testing/css/bulk-extraction.css');
    $bulkResult = $service->extract($testDir, $bulkCssFile);
    
    expect($bulkResult['processed'])->toBe(2);
    expect($bulkResult['new_rules'])->toBe(2);
    
    // Capture the class names generated during bulk extraction
    $file1Bulk = File::get($file1);
    $file2Bulk = File::get($file2);
    
    // Extract class names with file hashes
    preg_match('/class="(TW-[a-f0-9]{4}-test-one)"/', $file1Bulk, $bulkMatch1);
    preg_match('/class="(TW-[a-f0-9]{4}-test-two)"/', $file2Bulk, $bulkMatch2);
    
    expect($bulkMatch1)->toHaveCount(2); // Full match + capture group
    expect($bulkMatch2)->toHaveCount(2);
    
    $bulkClass1 = $bulkMatch1[1];
    $bulkClass2 = $bulkMatch2[1];
    
    // Extract the file hash from each
    preg_match('/TW-([a-f0-9]{4})-/', $bulkClass1, $hash1);
    preg_match('/TW-([a-f0-9]{4})-/', $bulkClass2, $hash2);
    
    $bulkHash1 = $hash1[1];
    $bulkHash2 = $hash2[1];
    
    // Restore both files
    $service->inject($testDir, $bulkCssFile);
    
    // INDIVIDUAL EXTRACTION: Extract each file separately
    $individualCssFile = storage_path('app/testing/css/individual-extraction.css');
    
    // Extract file 1 individually
    $service->extract($file1, $individualCssFile);
    $file1Individual = File::get($file1);
    preg_match('/class="(TW-[a-f0-9]{4}-test-one)"/', $file1Individual, $individualMatch1);
    $individualClass1 = $individualMatch1[1];
    preg_match('/TW-([a-f0-9]{4})-/', $individualClass1, $hash1Individual);
    $individualHash1 = $hash1Individual[1];
    
    // Restore and extract file 2 individually
    $service->inject($file1, $individualCssFile);
    $service->extract($file2, $individualCssFile);
    $file2Individual = File::get($file2);
    preg_match('/class="(TW-[a-f0-9]{4}-test-two)"/', $file2Individual, $individualMatch2);
    $individualClass2 = $individualMatch2[1];
    preg_match('/TW-([a-f0-9]{4})-/', $individualClass2, $hash2Individual);
    $individualHash2 = $hash2Individual[1];
    
    // ASSERTIONS: File hashes should be IDENTICAL whether extracted in bulk or individually
    expect($bulkHash1)->toBe($individualHash1)
        ->and($bulkClass1)->toBe($individualClass1)
        ->and($bulkHash2)->toBe($individualHash2)
        ->and($individualClass2)->toBe($bulkClass2);
    
    // Also verify that different files have different hashes
    expect($bulkHash1)->not->toBe($bulkHash2);
    
    // Clean up
    File::delete($bulkCssFile);
    File::delete($individualCssFile);
});

it('handles duplicate class lists across multiple files correctly', function () {
    // Create multiple files with the same class list
    $dir1 = $this->testRoot . '/dir1';
    $dir2 = $this->testRoot . '/dir2';
    
    File::ensureDirectoryExists($dir1);
    File::ensureDirectoryExists($dir2);
    
    $file1 = $dir1 . '/card1.blade.php';
    $file2 = $dir2 . '/card2.blade.php';
    $file3 = $dir2 . '/card3.blade.php';
    
    // All three files have the SAME class list
    $sharedContent = '<div class="__shared__ bg-white p-4 rounded shadow __">Content</div>';
    
    File::put($file1, $sharedContent);
    File::put($file2, $sharedContent);
    File::put($file3, $sharedContent);
    
    $service = app(TailwindExtractorService::class);
    
    // Extract all files
    $result = $service->extract($this->testRoot, $this->cssFile);
    
    expect($result['processed'])->toBe(3);
    
    // Each file should have its own unique hash
    $content1 = File::get($file1);
    $content2 = File::get($file2);
    $content3 = File::get($file3);
    
    preg_match('/TW-([a-f0-9]{4})-shared/', $content1, $hash1);
    preg_match('/TW-([a-f0-9]{4})-shared/', $content2, $hash2);
    preg_match('/TW-([a-f0-9]{4})-shared/', $content3, $hash3);
    
    // Files in different directories should have different hashes
    expect($hash1[1])->not->toBe($hash2[1]);
    
    // Files in the same directory could have the same hash (both in dir2)
    // But each file gets its own hash based on its path
    expect($hash2[1])->not->toBe($hash3[1]);
    
    // Verify CSS has all three rules
    $cssContent = File::get($this->cssFile);
    $ruleCount = substr_count($cssContent, '@apply bg-white p-4 rounded shadow');
    expect($ruleCount)->toBe(3);
    
    // Restore all files
    $restoreResult = $service->inject($this->testRoot, $this->cssFile);
    expect($restoreResult['injected'])->toBe(3);
    
    // All should be restored to the same content
    $restored1 = File::get($file1);
    $restored2 = File::get($file2);
    $restored3 = File::get($file3);
    
    expect($restored1)->toBe($sharedContent)
        ->and($restored2)->toBe($sharedContent)
        ->and($restored3)->toBe($sharedContent);
});

it('preserves prefix class names during multi-directory extraction and restore', function () {
    // Test the prefix bug fix in multi-directory scenario
    $dir1 = $this->testRoot . '/prefix_test1';
    $dir2 = $this->testRoot . '/prefix_test2';
    
    File::ensureDirectoryExists($dir1);
    File::ensureDirectoryExists($dir2);
    
    $file1 = $dir1 . '/buttons.blade.php';
    $file2 = $dir2 . '/cards.blade.php';
    
    File::put($file1, <<<'BLADE'
<button class="__btn__ px-4 py-2 __">Base</button>
<button class="__btn-lg__ px-6 py-3 __">Large</button>
<button class="__btn-lg-primary__ px-8 py-4 bg-blue-500 __">Primary</button>
BLADE
    );
    
    File::put($file2, <<<'BLADE'
<div class="__card__ p-4 rounded __">Base</div>
<div class="__card-shadow__ p-4 rounded shadow-lg __">Shadow</div>
BLADE
    );
    
    $service = app(TailwindExtractorService::class);
    
    // Extract all at once
    $result = $service->extract($this->testRoot, $this->cssFile);
    expect($result['processed'])->toBe(2);
    expect($result['new_rules'])->toBe(5); // 3 buttons + 2 cards
    
    // Restore all at once
    $restoreResult = $service->inject($this->testRoot, $this->cssFile);
    expect($restoreResult['injected'])->toBe(5);
    
    // Verify no corruption occurred
    $restored1 = File::get($file1);
    $restored2 = File::get($file2);
    
    // Check file1 - buttons
    expect($restored1)->toContain('__btn__ px-4 py-2 __')
        ->and($restored1)->toContain('__btn-lg__ px-6 py-3 __')
        ->and($restored1)->toContain('__btn-lg-primary__ px-8 py-4 bg-blue-500 __')
        ->and($restored1)->not->toContain('-lg">') // No corruption
        ->and($restored1)->not->toContain('-primary">'); // No corruption
    
    // Check file2 - cards
    expect($restored2)->toContain('__card__ p-4 rounded __')
        ->and($restored2)->toContain('__card-shadow__ p-4 rounded shadow-lg __')
        ->and($restored2)->not->toContain('-shadow">'); // No corruption
});
