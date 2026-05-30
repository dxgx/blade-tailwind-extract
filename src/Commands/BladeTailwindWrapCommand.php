<?php

namespace Dxgx\BladeTailwindExtract\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BladeTailwindWrapCommand extends Command
{
    protected $signature = 'dg:blade-tailwind:wrap
                            {view : The view file path (relative to resources/views)}
                            {--min=3 : Minimum number of classes to trigger wrapping}
                            {--skip-prefix=TW : Skip class lists containing classes with this prefix}
                            {--dry-run : Preview changes without modifying the file}';

    protected $description = 'Wrap Tailwind class lists in Blade templates with generated identifiers';

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

    public function handle(): int
    {
        $viewPath = $this->argument('view');
        $minClasses = (int) $this->option('min');
        $skipPrefix = $this->option('skip-prefix');
        $dryRun = $this->option('dry-run');

        // Reset mapping for each run
        $this->classListMap = [];
        $this->counter = 1;
        $this->changeLog = [];

        // Resolve view path
        $fullPath = $this->resolveViewPath($viewPath);

        if (! File::exists($fullPath)) {
            $this->error("View file not found: {$fullPath}");

            return self::FAILURE;
        }

        $content = File::get($fullPath);
        $originalContent = $content;

        // Process different class patterns
        $content = $this->processClassAttributes($content, $minClasses, $skipPrefix);

        if ($content === $originalContent) {
            $this->info('No changes needed - no matching class lists found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN - Changes not applied');
            $this->line('');
            $this->showChangeSummary();
        } else {
            File::put($fullPath, $content);
            $this->info("✓ File modified: {$fullPath}");
            $this->showChangeSummary();
        }

        return self::SUCCESS;
    }

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
        // Pattern 1: class="..." (including wire:class, :class, x-bind:class)
        $content = preg_replace_callback(
            '/(\s(?:wire:)?(?:x-bind:)?:?class\s*=\s*["\'])([^"\']*)(["\'])/s',
            fn ($matches) => $this->wrapIfNeeded($matches, $minClasses, $skipPrefix, 'simple'),
            $content
        );

        // Pattern 2: @class([...])
        $content = preg_replace_callback(
            '/@class\(\[\s*(["\'])([^"\']*)(["\'])/s',
            fn ($matches) => $this->wrapIfNeeded($matches, $minClasses, $skipPrefix, 'directive'),
            $content
        );

        return $content;
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
