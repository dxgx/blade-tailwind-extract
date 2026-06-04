<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Dxgx\BladeTailwindExtract\Commands\Concerns\HandlesBulkOperations;
use Dxgx\BladeTailwindExtract\TailwindExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BladeTailwindWrapCommand extends Command
{
    use HandlesBulkOperations;

    protected $signature = 'dg:blade-tailwind:wrap
                            {target? : Target to process (optional). Accepts: (1) File path: resources/views/components/card.blade.php, (2) Directory: ./resources/views (recursive), (3) Pattern: *preview* or *card*.blade.php, (4) Multiple: card.blade.php,list.blade.php. If omitted, processes all files in search_path}
                            {--min=3 : Minimum number of classes to trigger wrapping}
                            {--skip-prefix=TW : Skip class lists containing classes with this prefix}
                            {--dry-run : Preview changes without modifying the file}
                            {--yy : Skip all confirmations when processing all files (no target)}';

    protected $description = 'Wrap Tailwind class lists in Blade templates with generated identifiers';

    protected TailwindExtractorService $extractor;

    private array $adjectives = [
        'happy', 'sunny', 'quick', 'lazy', 'brave', 'calm', 'eager', 'gentle',
        'jolly', 'kind', 'lively', 'nice', 'polite', 'proud', 'silly', 'witty',
        'fancy', 'smart', 'wild', 'zany', 'cool', 'fair', 'fine', 'good',
        'wise', 'rich', 'safe', 'tidy', 'busy', 'easy',
    ];

    private array $nouns = [
        'cat', 'dog', 'fox', 'owl', 'bee', 'ant', 'bat', 'cow',
        'elk', 'emu', 'hen', 'jay', 'pig', 'ram', 'rat', 'yak',
        'bear', 'deer', 'duck', 'fish', 'goat', 'hawk', 'lion', 'seal',
        'swan', 'wolf', 'crab', 'dove', 'frog', 'moth',
    ];

    private int $counter = 1;

    private array $classListMap = [];

    private array $changeLog = [];

    /**
     * Patterns that should never be wrapped.
     * If any class in the list contains these patterns, skip wrapping.
     */
    private array $neverWrapPatterns = [
        '__',
        'material-symbols-outlined',
        'TW-',
    ];

    /**
     * Create a new command instance.
     */
    public function __construct(TailwindExtractorService $extractor)
    {
        parent::__construct();
        $this->extractor = $extractor;
    }

    public function handle(): int
    {
        $target = $this->argument('target');
        $minClasses = (int) $this->option('min');
        $skipPrefix = $this->option('skip-prefix');
        $dryRun = $this->option('dry-run');

        // Reset mapping for each run
        $this->classListMap = [];
        $this->counter = 1;
        $this->changeLog = [];

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
            if (!$this->confirmBulkOperation('wrap', $files, $searchPath, $skipConfirmations)) {
                $this->comment('Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Use search_path as the target
            $target = $searchPath;
        }

        try {
            // Get files using the extractor service's pattern matching
            $files = $this->extractor->getBladeFiles($target);
            
            if (empty($files)) {
                $this->warn('⚠️  No files found matching the target.');
                return self::SUCCESS;
            }

            $this->info("🔍 Wrapping Tailwind classes in " . count($files) . " file(s)...");
            $this->newLine();

            $filesProcessed = 0;
            $filesModified = 0;

            foreach ($files as $filePath) {
                $result = $this->processFile($filePath, $minClasses, $skipPrefix, $dryRun);
                $filesProcessed++;
                
                if ($result) {
                    $filesModified++;
                }
            }

            $this->newLine();
            $this->info("✅ Processed {$filesProcessed} file(s), modified {$filesModified}");
            
            if ($filesModified > 0) {
                $this->newLine();
                $this->showChangeSummary();
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Process a single file
     * 
     * @return bool True if file was modified, false otherwise
     */
    private function processFile(string $fullPath, int $minClasses, string $skipPrefix, bool $dryRun): bool
    {
        if (!File::exists($fullPath)) {
            $this->error("View file not found: {$fullPath}");
            return false;
        }

        $content = File::get($fullPath);
        $originalContent = $content;

        // Process different class patterns
        $content = $this->processClassAttributes($content, $minClasses, $skipPrefix);

        if ($content === $originalContent) {
            return false;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would modify: {$fullPath}");
        } else {
            File::put($fullPath, $content);
            $this->info("✓ Modified: {$fullPath}");
        }

        return true;
    }

    /**
     * Resolve view path (for backward compatibility with dot notation)
     * @deprecated This method is kept for backward compatibility but no longer used in handle()
     */
    private function resolveViewPath(string $viewPath): string
    {
        // Remove .blade.php extension if provided (must do before replacing dots)
        $viewPath = str_replace('.blade.php', '', $viewPath);

        // Convert dot notation to directory separators
        $viewPath = str_replace('.', '/', $viewPath);

        return resource_path("views/{$viewPath}.blade.php");
    }

    private function processClassAttributes(string $content, int $minClasses, string $skipPrefix): string
    {
        // Pattern 3: @class conditional strings - Process ALL quoted strings in @class arrays
        // Do this FIRST to handle complex @class arrays before simple pattern matches
        $content = $this->processAtClassConditionals($content, $minClasses, $skipPrefix);

        // Pattern 4: :class ternary expressions - Handle dynamic :class with ternary operator
        // Do this BEFORE Pattern 1 so it doesn't consume :class attributes
        $content = $this->processClassTernary($content, $minClasses, $skipPrefix);

        // Pattern 1: Static class="..." and wire:class="..." (NOT :class or x-bind:class which are dynamic)
        // Use separate patterns for double and single quotes to handle arbitrary values with quotes
        // Process double-quoted attributes
        $content = preg_replace_callback(
            '/(\s(?:wire:)?class\s*=\s*")([^"]*)(")/s',
            fn ($matches) => $this->wrapIfNeeded($matches, $minClasses, $skipPrefix, 'simple'),
            $content
        );
        
        // Process single-quoted attributes
        $content = preg_replace_callback(
            '/(\s(?:wire:)?class\s*=\s*\')([^\']*)(\')/s',
            fn ($matches) => $this->wrapIfNeeded($matches, $minClasses, $skipPrefix, 'simple'),
            $content
        );

        // Pattern 2: @class([...]) - First string only (for backward compatibility with simple arrays)
        // This only matches if Pattern 3 didn't already process it
        // Process double-quoted strings
        $content = preg_replace_callback(
            '/@class\(\[\s*"([^"]*)"/s',
            function ($matches) use ($minClasses, $skipPrefix) {
                // Reformat to match wrapIfNeeded expected format
                $reformatted = [
                    0 => $matches[0],
                    1 => '"',  // Just the quote, wrapIfNeeded will add '@class([' prefix
                    2 => $matches[1],  // The class list
                    3 => '"',
                ];
                return $this->wrapIfNeeded($reformatted, $minClasses, $skipPrefix, 'directive');
            },
            $content
        );
        
        // Process single-quoted strings
        $content = preg_replace_callback(
            '/@class\(\[\s*\'([^\']*)\'/s',
            function ($matches) use ($minClasses, $skipPrefix) {
                // Reformat to match wrapIfNeeded expected format
                $reformatted = [
                    0 => $matches[0],
                    1 => '\'',  // Just the quote, wrapIfNeeded will add '@class([' prefix
                    2 => $matches[1],  // The class list
                    3 => '\'',
                ];
                return $this->wrapIfNeeded($reformatted, $minClasses, $skipPrefix, 'directive');
            },
            $content
        );

        return $content;
    }

    /**
     * Process all conditional strings within @class([...]) arrays
     * Example: @class(['base classes', 'conditional' => $condition])
     */
    private function processAtClassConditionals(string $content, int $minClasses, string $skipPrefix): string
    {
        return preg_replace_callback(
            '/@class\(\[([^\]]+)\]\)/s',
            function ($matches) use ($minClasses, $skipPrefix) {
                $arrayContent = $matches[1];
                $originalArrayContent = $arrayContent;
                
                // Find all quoted strings (both simple and conditional keys)
                // Match: 'string' or "string", optionally followed by => condition
                // We need to be careful to match the full conditional part
                $processed = preg_replace_callback(
                    '/(["\'])([^"\']*)\1(\s*=>\s*[^,]+)?/s',
                    function ($stringMatches) use ($minClasses, $skipPrefix) {
                        $quote = $stringMatches[1];
                        $classList = $stringMatches[2];
                        $conditionalPart = $stringMatches[3] ?? '';
                        
                        // Skip if classList contains Blade variable interpolation
                        if (str_contains($classList, '{{') || str_contains($classList, '}}')) {
                            return $stringMatches[0];
                        }
                        
                        // Skip if already wrapped
                        if (preg_match('/__[a-z]+-[a-z]+-\d+__/', $classList)) {
                            return $stringMatches[0];
                        }
                        
                        // Check for never-wrap patterns
                        foreach ($this->neverWrapPatterns as $pattern) {
                            if (str_contains($classList, $pattern)) {
                                return $stringMatches[0];
                            }
                        }
                        
                        // Parse and count classes
                        $classes = $this->parseClasses($classList);
                        if (count($classes) < $minClasses) {
                            return $stringMatches[0];
                        }
                        
                        // Check for skip prefix
                        if (! empty($skipPrefix)) {
                            foreach ($classes as $class) {
                                if (str_starts_with($class, $skipPrefix)) {
                                    return $stringMatches[0];
                                }
                            }
                        }
                        
                        // Generate or reuse wrapper name
                        $normalizedClassList = $this->normalizeWhitespace($classList);
                        if (! isset($this->classListMap[$normalizedClassList])) {
                            $this->classListMap[$normalizedClassList] = $this->generateWrapperName();
                        }
                        $wrapperName = $this->classListMap[$normalizedClassList];
                        
                        // Wrap the class list
                        $wrappedClassList = "__{$wrapperName}__ {$classList} __";
                        
                        // Log the change
                        $this->changeLog[] = [
                            'original' => $classList,
                            'wrapped' => $wrappedClassList,
                            'wrapper' => $wrapperName,
                        ];
                        
                        return $quote . $wrappedClassList . $quote . $conditionalPart;
                    },
                    $arrayContent
                );
                
                // Only return modified version if something changed
                if ($processed !== $originalArrayContent) {
                    return '@class([' . $processed . '])';
                }
                
                return $matches[0];
            },
            $content
        );
    }

    /**
     * Process ternary expressions in :class bindings
     * Example: :class="condition ? 'classes-a' : 'classes-b'"
     * Also handles simple :class="classes" bindings
     */
    private function processClassTernary(string $content, int $minClasses, string $skipPrefix): string
    {
        // Process double-quoted :class attributes
        $content = preg_replace_callback(
            '/(\s(?:wire:)?(?:x-bind:)?:class\s*=\s*")((?:[^"]|(?<=\\\\)")*?)(")/s',
            function ($matches) use ($minClasses, $skipPrefix) {
                return $this->processClassTernaryMatch($matches, $minClasses, $skipPrefix, '"', "'");
            },
            $content
        );
        
        // Process single-quoted :class attributes
        $content = preg_replace_callback(
            "/(\s(?:wire:)?(?:x-bind:)?:class\s*=\s*')((?:[^']|(?<=\\\\)')*?)(')/s",
            function ($matches) use ($minClasses, $skipPrefix) {
                return $this->processClassTernaryMatch($matches, $minClasses, $skipPrefix, "'", '"');
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Helper method to process a single :class attribute match
     */
    private function processClassTernaryMatch(array $matches, int $minClasses, string $skipPrefix, string $outerQuote, string $innerQuote): string
    {
        $prefix = $matches[1];
        $expression = $matches[2];
        $suffix = $matches[3];
        
        // Check if this contains a ternary (has ? character)
        $hasTernary = str_contains($expression, '?');
        
        if ($hasTernary) {
            // Extract the two branches of the ternary
            // Pattern: condition ? 'true-branch' : 'false-branch'
            $innerPattern = '/' . preg_quote($innerQuote, '/') . '([^' . preg_quote($innerQuote, '/') . ']+)' . preg_quote($innerQuote, '/') . '/s';
            
            $processed = preg_replace_callback(
                $innerPattern,
                function ($branchMatches) use ($minClasses, $skipPrefix, $innerQuote) {
                    $classList = $branchMatches[1];
                    
                    // Skip if classList contains Blade variable interpolation
                    if (str_contains($classList, '{{') || str_contains($classList, '}}')) {
                        return $branchMatches[0];
                    }
                    
                    // Skip if already wrapped
                    if (preg_match('/__[a-z]+-[a-z]+-\d+__/', $classList)) {
                        return $branchMatches[0];
                    }
                    
                    // Check for never-wrap patterns
                    foreach ($this->neverWrapPatterns as $pattern) {
                        if (str_contains($classList, $pattern)) {
                            return $branchMatches[0];
                        }
                    }
                    
                    // Parse and count classes
                    $classes = $this->parseClasses($classList);
                    if (count($classes) < $minClasses) {
                        return $branchMatches[0];
                    }
                    
                    // Check for skip prefix
                    if (! empty($skipPrefix)) {
                        foreach ($classes as $class) {
                            if (str_starts_with($class, $skipPrefix)) {
                                return $branchMatches[0];
                            }
                        }
                    }
                    
                    // Generate or reuse wrapper name
                    $normalizedClassList = $this->normalizeWhitespace($classList);
                    if (! isset($this->classListMap[$normalizedClassList])) {
                        $this->classListMap[$normalizedClassList] = $this->generateWrapperName();
                    }
                    $wrapperName = $this->classListMap[$normalizedClassList];
                    
                    // Wrap the class list
                    $wrappedClassList = "__{$wrapperName}__ {$classList} __";
                    
                    // Log the change
                    $this->changeLog[] = [
                        'original' => $classList,
                        'wrapped' => $wrappedClassList,
                        'wrapper' => $wrapperName,
                    ];
                    
                    return $innerQuote . $wrappedClassList . $innerQuote;
                },
                $expression
            );
            
            return $prefix . $processed . $suffix;
        } else {
            // Simple :class="static classes" - reassemble the match format for wrapIfNeeded
            $reformattedMatch = [
                0 => $matches[0],
                1 => $prefix,
                2 => $expression,
                3 => $suffix,
            ];
            return $this->wrapIfNeeded($reformattedMatch, $minClasses, $skipPrefix, 'simple');
        }
    }

    private function wrapIfNeeded(array $matches, int $minClasses, string $skipPrefix, string $type): string
    {
        if ($type === 'simple') {
            $prefix = $matches[1];
            $classList = $matches[2];
            $suffix = $matches[3];
        } else { // directive
            $prefix = '@class(['.$matches[1];
            $classList = $matches[2];
            $suffix = $matches[3];
        }

        // Skip if classList contains Blade variable interpolation (dynamic classes)
        if (str_contains($classList, '{{') || str_contains($classList, '}}')) {
            return $matches[0];
        }

        // Check for never-wrap patterns (configured patterns to always skip)
        foreach ($this->neverWrapPatterns as $pattern) {
            if (str_contains($classList, $pattern)) {
                return $matches[0];
            }
        }

        // Parse classes
        $classes = $this->parseClasses($classList);

        // Check if we should skip
        if (count($classes) < $minClasses) {
            return $matches[0];
        }

        // Check for skip prefix
        if (! empty($skipPrefix)) {
            foreach ($classes as $class) {
                if (str_starts_with($class, $skipPrefix)) {
                    return $matches[0];
                }
            }
        }

        // Generate or reuse wrapper name for this class list
        // Normalize whitespace for proper deduplication
        $normalizedClassList = $this->normalizeWhitespace($classList);
        if (! isset($this->classListMap[$normalizedClassList])) {
            $this->classListMap[$normalizedClassList] = $this->generateWrapperName();
        }
        $wrapperName = $this->classListMap[$normalizedClassList];

        // Wrap the class list
        $wrappedClassList = "__{$wrapperName}__ {$classList} __";

        // Log the change
        $this->changeLog[] = [
            'original' => $classList,
            'wrapped' => $wrappedClassList,
            'wrapper' => $wrapperName,
        ];

        return $prefix.$wrappedClassList.$suffix;
    }

    private function normalizeWhitespace(string $classList): string
    {
        // Trim edges and collapse multiple spaces into single space
        return preg_replace('/\s+/', ' ', trim($classList));
    }

    private function parseClasses(string $classList): array
    {
        // Split by whitespace and filter empty
        return array_filter(
            preg_split('/\s+/', trim($classList)),
            fn ($class) => ! empty($class)
        );
    }

    private function generateWrapperName(): string
    {
        $adjective = $this->adjectives[array_rand($this->adjectives)];
        $noun = $this->nouns[array_rand($this->nouns)];

        $name = "{$adjective}-{$noun}-{$this->counter}";
        $this->counter++;

        return $name;
    }

    private function showChangeSummary(): void
    {
        if (empty($this->changeLog)) {
            return;
        }

        $this->line('');
        $this->info('=== Changes Summary ===');
        $this->line('');

        // Group by wrapper name
        $grouped = [];
        foreach ($this->changeLog as $change) {
            $wrapper = $change['wrapper'];
            if (! isset($grouped[$wrapper])) {
                $grouped[$wrapper] = [
                    'original' => $change['original'],
                    'wrapped' => $change['wrapped'],
                    'count' => 0,
                ];
            }
            $grouped[$wrapper]['count']++;
        }

        foreach ($grouped as $wrapper => $data) {
            $this->line("<fg=yellow>__{$wrapper}__</> ({$data['count']} occurrence".($data['count'] > 1 ? 's' : '').')');
            $this->line("  <fg=gray>Before:</>  {$data['original']}");
            $this->line("  <fg=green>After:</>   {$data['wrapped']}");
            $this->line('');
        }

        $totalChanges = count($this->changeLog);
        $uniqueWrappers = count($grouped);
        $this->info("Total: {$totalChanges} class list".($totalChanges > 1 ? 's' : '')." wrapped with {$uniqueWrappers} unique wrapper".($uniqueWrappers > 1 ? 's' : ''));
    }
}
