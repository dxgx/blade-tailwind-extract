<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Dxgx\BladeTailwindExtract\Commands\Concerns\HandlesBulkOperations;
use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Console\Command;

class BladeTailwindExtractCommand extends Command
{
    use HandlesBulkOperations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dg:blade-tailwind:extract
                            {target? : Target to process (optional). Accepts: (1) File path: resources/views/components/card.blade.php, (2) Directory: ./resources/views (recursive), (3) Pattern: *preview* or *card*.blade.php, (4) Multiple: card.blade.php,list.blade.php. If omitted, processes all files in search_path}
                            {--css-file= : Override the CSS output file path}
                            {--yy : Skip all confirmations when processing all files (no target)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract Tailwind CSS classes from Blade templates into @apply rules';

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
            if (!$this->confirmBulkOperation('extract', $files, $searchPath, $skipConfirmations)) {
                $this->comment('Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Use search_path as the target
            $target = $searchPath;
        }

        try {
            return $this->handleExtract($target, $cssFile);
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

        // Run PHP lint check on all files
        $files = $this->extractor->getBladeFiles($target);
        if (!empty($files)) {
            $this->info("🔧 Running PHP syntax check...");
            $lintErrors = $this->lintPhpFiles($files);
            
            if (!empty($lintErrors)) {
                $this->newLine();
                $this->error("❌ PHP syntax errors found in " . count($lintErrors) . " file(s):");
                $this->newLine();
                
                foreach ($lintErrors as $file => $error) {
                    $this->line("   • " . $file);
                    $this->line("     " . trim($error));
                }
                
                $this->newLine();
                $this->error("Fix syntax errors before extracting.");
                return self::FAILURE;
            }
            
            $this->info("✓ All files passed PHP syntax check");
            $this->newLine();
        }

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
        $this->comment('💡 Tip: Run "dg:blade-tailwind:restore" to reverse this operation');

        return self::SUCCESS;
    }
}
