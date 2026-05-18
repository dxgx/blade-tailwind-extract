<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Console\Command;

class BladeTailwindExtractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dgtool:blade-tailwind-extract
                            {mode : The operation mode: extract, inject, restore, e, or r}
                            {target : Target file, directory, or pattern to process}
                            {--css-file= : Override the CSS output file path}';

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
}
