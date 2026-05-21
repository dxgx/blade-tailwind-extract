<?php

namespace Dxgx\BladeTailwindExtract\Commands\Concerns;

/**
 * Shared functionality for bulk file operations in Tailwind extraction commands.
 * 
 * This trait provides common methods for file discovery, PHP linting,
 * and user confirmations when processing multiple Blade templates.
 */
trait HandlesBulkOperations
{
    /**
     * Find all .blade.php files recursively in the given directory
     */
    protected function findAllBladeFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $ignoredDirs = config('dg-blade-tailwind-extract.ignored_directories', []);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
                $filePath = $file->getPathname();
                
                // Check if file is in an ignored directory
                $shouldIgnore = false;
                foreach ($ignoredDirs as $ignoredDir) {
                    if (str_contains($filePath, $ignoredDir)) {
                        $shouldIgnore = true;
                        break;
                    }
                }
                
                if (!$shouldIgnore) {
                    $files[] = $filePath;
                }
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Show confirmation prompts and file list for bulk operations
     */
    protected function confirmBulkOperation(string $mode, array $files, string $searchPath, bool $skipConfirmations = false): bool
    {
        // Skip all confirmations if --yy flag is provided
        if ($skipConfirmations) {
            return true;
        }

        $fileCount = count($files);
        $operationName = $mode === 'extract' ? 'extract Tailwind classes from' : 'restore Tailwind classes into';

        $this->newLine();
        $this->warn("⚠️  You are about to $operationName ALL files in: $searchPath");
        $this->newLine();

        // First confirmation
        if (!$this->confirm("Are you sure you want to process $fileCount file(s)?", false)) {
            return false;
        }

        // For extract mode, filter files to show only those with extractable patterns
        $filesToShow = $files;
        if ($mode === 'extract') {
            $filesToShow = array_filter($files, function($file) {
                return $this->extractor->hasExtractablePatterns($file);
            });
            
            if (empty($filesToShow)) {
                $this->warn('⚠️  No files found with extractable patterns (__name__ classes __)');
                return false;
            }
        }

        $this->newLine();
        $this->info("📄 Files to be processed:");
        if ($mode === 'extract') {
            $filteredCount = count($filesToShow);
            $this->comment("   (Showing only $filteredCount file(s) with extractable patterns)");
        }
        $this->newLine();

        // Display file list (max 50)
        $displayLimit = 50;
        $displayFiles = array_slice($filesToShow, 0, $displayLimit);
        
        foreach ($displayFiles as $file) {
            $this->line("   • " . $file);
        }

        $showCount = count($filesToShow);
        if ($showCount > $displayLimit) {
            $remaining = $showCount - $displayLimit;
            $this->line("   ... and $remaining more file(s)");
        }

        $this->newLine();

        // Second confirmation
        return $this->confirm("Proceed with $mode operation on these files?", false);
    }

    /**
     * Lint PHP files for syntax errors
     * 
     * @param array $files Array of file paths to check
     * @return array Array of files with errors (file => error message)
     */
    protected function lintPhpFiles(array $files): array
    {
        $errors = [];
        
        foreach ($files as $file) {
            $output = [];
            $returnCode = 0;
            
            // Run php -l to check syntax
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                $errors[$file] = implode("\n", $output);
            }
        }
        
        return $errors;
    }
}
