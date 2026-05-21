<?php

use Dxgx\BladeTailwindExtract\Commands\BladeTailwindExtractCommand;
use Dxgx\BladeTailwindExtract\Commands\BladeTailwindRestoreCommand;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Set up test directories
    $this->testViewPath = base_path('tests/fixtures/views');
    $this->testCssPath = base_path('tests/fixtures/css/test-tw.css');
    
    File::ensureDirectoryExists(dirname($this->testViewPath));
    File::ensureDirectoryExists(dirname($this->testCssPath));
});

afterEach(function () {
    // Clean up test files
    if (File::exists($this->testCssPath)) {
        File::delete($this->testCssPath);
    }
});

it('can run extract command with a specific target', function () {
    // Create a test blade file
    $testFile = $this->testViewPath . '/test.blade.php';
    File::put($testFile, '<div class="__wrapper__ flex items-center __"></div>');
    
    $this->artisan(BladeTailwindExtractCommand::class, [
        'target' => $testFile,
        '--css-file' => $this->testCssPath,
    ])
    ->expectsOutput('🔍 Extracting Tailwind classes from: ' . $testFile)
    ->assertSuccessful();
    
    expect(File::exists($this->testCssPath))->toBeTrue();
    
    // Clean up
    File::delete($testFile);
});

it('prompts for confirmation when no target is provided', function () {
    // Override config for this test
    config(['blade-tailwind-extract.search_path' => $this->testViewPath]);
    
    // Create some test blade files
    File::put($this->testViewPath . '/file1.blade.php', '<div class="__test__ bg-red-500 __"></div>');
    File::put($this->testViewPath . '/file2.blade.php', '<div class="__test__ bg-blue-500 __"></div>');
    
    $this->artisan(BladeTailwindExtractCommand::class, [
        '--css-file' => $this->testCssPath,
    ])
    ->expectsQuestion('Are you sure you want to process 2 file(s)?', 'no')
    ->expectsOutput('Operation cancelled.')
    ->assertSuccessful();
    
    // Clean up
    File::delete($this->testViewPath . '/file1.blade.php');
    File::delete($this->testViewPath . '/file2.blade.php');
});

it('proceeds with operation when both confirmations are accepted', function () {
    // Override config for this test
    config(['blade-tailwind-extract.search_path' => $this->testViewPath]);
    
    // Create a test blade file
    File::put($this->testViewPath . '/file1.blade.php', '<div class="__test__ bg-red-500 __"></div>');
    
    $this->artisan(BladeTailwindExtractCommand::class, [
        '--css-file' => $this->testCssPath,
    ])
    ->expectsQuestion('Are you sure you want to process 1 file(s)?', 'yes')
    ->expectsQuestion('Proceed with extract operation on these files?', 'yes')
    ->assertSuccessful();
    
    // Verify CSS file was created
    expect(File::exists($this->testCssPath))->toBeTrue();
    
    // Clean up
    File::delete($this->testViewPath . '/file1.blade.php');
});

it('shows file list in confirmation prompt', function () {
    // Override config for this test
    config(['blade-tailwind-extract.search_path' => $this->testViewPath]);
    
    // Create test blade files
    $file1 = $this->testViewPath . '/file1.blade.php';
    $file2 = $this->testViewPath . '/file2.blade.php';
    
    File::put($file1, '<div class="__test__ bg-red-500 __"></div>');
    File::put($file2, '<div class="__test__ bg-blue-500 __"></div>');
    
    $this->artisan(BladeTailwindExtractCommand::class, [
        '--css-file' => $this->testCssPath,
    ])
    ->expectsQuestion('Are you sure you want to process 2 file(s)?', 'yes')
    ->expectsOutput('📄 Files to be processed:')
    ->expectsQuestion('Proceed with extract operation on these files?', 'no')
    ->assertSuccessful();
    
    // Clean up
    File::delete($file1);
    File::delete($file2);
});

it('works with restore command without target parameter', function () {
    // Override config for this test
    config(['blade-tailwind-extract.search_path' => $this->testViewPath]);
    
    // Create a test blade file with extracted class
    $testFile = $this->testViewPath . '/file1.blade.php';
    File::put($testFile, '<div class="TW-a40f-test"></div>');
    
    // Create CSS file with the rule
    File::put($this->testCssPath, ".TW-a40f-test {\n    @apply bg-red-500;\n}");
    
    $this->artisan(BladeTailwindRestoreCommand::class, [
        '--css-file' => $this->testCssPath,
    ])
    ->expectsQuestion('Are you sure you want to process 1 file(s)?', 'yes')
    ->expectsQuestion('Proceed with restore operation on these files?', 'yes')
    ->assertSuccessful();
    
    // Clean up
    File::delete($testFile);
});

it('handles empty search path gracefully', function () {
    // Override config with non-existent path
    $emptyPath = base_path('tests/fixtures/empty');
    config(['blade-tailwind-extract.search_path' => $emptyPath]);
    
    File::ensureDirectoryExists($emptyPath);
    
    $this->artisan(BladeTailwindExtractCommand::class, [
        '--css-file' => $this->testCssPath,
    ])
    ->expectsOutput("⚠️  No .blade.php files found in: $emptyPath")
    ->assertSuccessful();
    
    // Clean up
    File::deleteDirectory($emptyPath);
});

it('skips all confirmations when --yy flag is provided', function () {
    // Override config for this test
    config(['blade-tailwind-extract.search_path' => $this->testViewPath]);
    
    // Create test blade files
    File::put($this->testViewPath . '/file1.blade.php', '<div class="__test__ bg-red-500 __"></div>');
    File::put($this->testViewPath . '/file2.blade.php', '<div class="__test__ bg-blue-500 __"></div>');
    
    // Should not prompt for any confirmations
    $this->artisan(BladeTailwindExtractCommand::class, [
        '--css-file' => $this->testCssPath,
        '--yy' => true,
    ])
    ->doesntExpectOutput('Operation cancelled.')
    ->assertSuccessful();
    
    // Verify CSS file was created
    expect(File::exists($this->testCssPath))->toBeTrue();
    
    // Clean up
    File::delete($this->testViewPath . '/file1.blade.php');
    File::delete($this->testViewPath . '/file2.blade.php');
});
