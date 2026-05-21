<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Console\Command;

//TODO: somehow need to prevent adding non Tailwind classes, for example if there is class="__test__ fa fa-eye text-green-500 animate-pulse top-0 left-0 w-4 h-4 cursor-pointer;  __ tool should stop saying that fa fa-eye is not Tailwind class."


class BladeTailwindExtractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dg:blade-tailwind-extract
                            {mode : The operation mode: extract, inject, restore, e, or r}
                            {target? : Target to process (optional). Accepts: (1) File path: resources/views/components/card.blade.php, (2) Directory: ./resources/views (recursive), (3) Pattern: *preview* or *card*.blade.php, (4) Multiple: card.blade.php,list.blade.php. If omitted, processes all files in search_path}
                            {--css-file= : Override the CSS output file path}
                            {--yy : Skip all confirmations when processing all files (no target)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract or inject Tailwind CSS classes to/from Blade templates';

    protected TailwindExtractorService $extractor;

    /**
     * Create a new command instance.
     */
    public function __construct(TailwindExtractorService $extractor)
    {
        parent::__construct();
        $this->extractor = $extractor;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = $this->argument('mode');
        $target = $this->argument('target');
        $cssFile = $this->option('css-file') ?? config('blade-tailwind-extract.css_output_path');

        // Normalize mode aliases
        $mode = match ($mode) {
            'e' => 'extract',
            'r', 'restore' => 'inject',
            default => $mode,
        };

        if (! in_array($mode, ['extract', 'inject'])) {
            $this->error('Invalid mode. Use: extract, inject, restore, e, or r');

            return self::FAILURE;
        }

        // If no target provided, process all files with confirmation
        if ($target === null) {
            $searchPath = config('blade-tailwind-extract.search_path');
            $files = $this->findAllBladeFiles($searchPath);
            
            if (empty($files)) {
                $this->warn("⚠️  No .blade.php files found in: $searchPath");
                return self::SUCCESS;
            }
            
            // Show confirmation and file list (skip if --yy flag is provided)
            $skipConfirmations = $this->option('yy');
            if (!$this->confirmBulkOperation($mode, $files, $searchPath, $skipConfirmations)) {
                $this->comment('Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Use search_path as the target
            $target = $searchPath;
        }

        try {
            if ($mode === 'extract') {
                return $this->handleExtract($target, $cssFile);
            } else {
                return $this->handleInject($target, $cssFile);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle extract mode
     */
    protected function handleExtract(string $target, string $cssFile): int
    {
        $this->info("🔍 Extracting Tailwind classes from: $target");
        $this->newLine();

        $result = $this->extractor->extract($target, $cssFile);

        if ($result['processed'] === 0) {
            $this->warn('⚠️  No files found to process.');

            return self::SUCCESS;
        }

        $this->info("✅ Processed {$result['processed']} file(s)");

        if ($result['new_rules'] > 0) {
            $this->info("📝 Added {$result['new_rules']} new CSS rule(s) to: $cssFile");
        } else {
            $this->comment('   No new rules to add (all classes already extracted)');
        }

        $this->newLine();
        $this->comment('💡 Tip: Run with "inject" or "restore" mode to reverse this operation');

        return self::SUCCESS;
    }

    /**
     * Handle inject mode
     */
    protected function handleInject(string $target, string $cssFile): int
    {
        $this->info("📥 Injecting Tailwind classes into: $target");
        $this->newLine();

        $result = $this->extractor->inject($target, $cssFile);

        if ($result['processed'] === 0) {
            $this->warn('⚠️  No files found to process.');

            return self::SUCCESS;
        }

        $this->info("✅ Processed {$result['processed']} file(s)");

        if ($result['injected'] > 0) {
            $this->info("🔄 Injected {$result['injected']} class(es) back into templates");
        } else {
            $this->comment('   No classes to inject (files already in development mode)');
        }

        $this->newLine();
        $this->comment('💡 Tip: Run with "extract" or "e" mode to re-extract after editing');

        return self::SUCCESS;
    }

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

        $ignoredDirs = config('blade-tailwind-extract.ignored_directories', []);

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
        $operationName = $mode === 'extract' ? 'extract Tailwind classes from' : 'inject Tailwind classes into';

        $this->newLine();
        $this->warn("⚠️  You are about to $operationName ALL files in: $searchPath");
        $this->newLine();

        // First confirmation
        if (!$this->confirm("Are you sure you want to process $fileCount file(s)?", false)) {
            return false;
        }

        $this->newLine();
        $this->info("📄 Files to be processed:");
        $this->newLine();

        // Display file list (max 50)
        $displayLimit = 50;
        $filesToShow = array_slice($files, 0, $displayLimit);
        
        foreach ($filesToShow as $file) {
            $this->line("   • " . $file);
        }

        if ($fileCount > $displayLimit) {
            $remaining = $fileCount - $displayLimit;
            $this->line("   ... and $remaining more file(s)");
        }

        $this->newLine();

        // Second confirmation
        return $this->confirm("Proceed with $mode operation on these files?", false);
    }
}
