<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Dxgx\BladeTailwindExtract\Commands\Concerns\HandlesBulkOperations;
use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Console\Command;

class BladeTailwindRestoreCommand extends Command
{
    use HandlesBulkOperations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dg:blade-tailwind:restore
                            {target? : Target to process (optional). Accepts: (1) File path: resources/views/components/card.blade.php, (2) Directory: ./resources/views (recursive), (3) Pattern: *preview* or *card*.blade.php, (4) Multiple: card.blade.php,list.blade.php. If omitted, processes all files in search_path}
                            {--css-file= : Override the CSS output file path}
                            {--yy : Skip all confirmations when processing all files (no target)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore inline Tailwind CSS classes from @apply rules back into Blade templates';

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
        $target = $this->argument('target');
        $cssFile = $this->option('css-file') ?? config('dg-blade-tailwind-extract.css_output_path');

        // If no target provided, process all files with confirmation
        if ($target === null) {
            $searchPath = config('dg-blade-tailwind-extract.search_path');
            $files = $this->findAllBladeFiles($searchPath);
            
            if (empty($files)) {
                $this->warn("⚠️  No .blade.php files found in: $searchPath");
                return self::SUCCESS;
            }
            
            // Show confirmation and file list (skip if --yy flag is provided)
            $skipConfirmations = $this->option('yy');
            if (!$this->confirmBulkOperation('restore', $files, $searchPath, $skipConfirmations)) {
                $this->comment('Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Use search_path as the target
            $target = $searchPath;
        }

        try {
            return $this->handleRestore($target, $cssFile);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Handle restore mode
     */
    protected function handleRestore(string $target, string $cssFile): int
    {
        $this->info("📥 Restoring Tailwind classes into: $target");
        $this->newLine();

        $result = $this->extractor->inject($target, $cssFile);

        if ($result['processed'] === 0) {
            $this->warn('⚠️  No files found to process.');
            return self::SUCCESS;
        }

        $this->info("✅ Processed {$result['processed']} file(s)");

        if ($result['injected'] > 0) {
            $this->info("🔄 Restored {$result['injected']} class(es) back into templates");
        } else {
            $this->comment('   No classes to restore (files already in development mode)');
        }

        $this->newLine();
        $this->comment('💡 Tip: Run "dg:blade-tailwind:extract" to re-extract after editing');

        return self::SUCCESS;
    }
}
