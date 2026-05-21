<?php

namespace Dxgx\BladeTailwindExtract;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class TailwindExtractorService
{
    protected array $config;

    protected string $classPrefix;

    protected int $hashLength;

    protected array $ignoredDirectories;

    protected array $reservedClasses;

    protected int $maxIterations;

    protected string $projectRoot;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->classPrefix = $config['class_prefix'] ?? 'TW';
        $this->hashLength = $config['hash_length'] ?? 4;
        $this->ignoredDirectories = $config['ignored_directories'] ?? [];
        $this->reservedClasses = $config['reserved_classes'] ?? ['group', 'peer'];
        $this->maxIterations = $config['max_iterations'] ?? 10;
        $this->projectRoot = base_path();
    }

    /**
     * Extract classes from blade files to CSS
     */
    public function extract(string $target, string $cssFile): array
    {
        $files = $this->getBladeFiles($target);

        if (empty($files)) {
            return ['processed' => 0, 'new_rules' => 0];
        }

        $existingRules = $this->readExistingCssRules($cssFile);
        $newRules = [];
        $fileRules = [];
        $classRegistry = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $originalContent = $content;
            $fileSpecificRules = [];

            // Extract from class="" attributes
            $content = $this->extractFromClassAttributes(
                $content,
                $originalContent,
                $file,
                $newRules,
                $existingRules,
                $fileSpecificRules,
                $classRegistry
            );

            // Extract from ->class([...]) method calls
            $content = $this->extractFromClassMethod(
                $content,
                $originalContent,
                $file,
                $newRules,
                $existingRules,
                $fileSpecificRules,
                $classRegistry
            );

            // Extract from @class([...]) directive calls
            $content = $this->extractFromAtClassDirective(
                $content,
                $originalContent,
                $file,
                $newRules,
                $existingRules,
                $fileSpecificRules,
                $classRegistry
            );

            if (! empty($fileSpecificRules)) {
                $fileRules[$file] = $fileSpecificRules;
            }

            file_put_contents($file, $content);
        }

        // Update CSS file with new rules
        if (! empty($newRules)) {
            $this->updateCssFile($cssFile, $fileRules, $newRules);
        }

        return [
            'processed' => count($files),
            'new_rules' => count($newRules),
        ];
    }

    /**
     * Inject classes from CSS back into blade files
     */
    public function inject(string $target, string $cssFile): array
    {
        $files = $this->getBladeFiles($target);

        if (empty($files)) {
            return ['processed' => 0, 'injected' => 0];
        }

        $rules = $this->readExistingCssRules($cssFile);

        if (empty($rules)) {
            return ['processed' => 0, 'injected' => 0];
        }

        $totalInjected = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $injectedClasses = [];

            // Inject into class="" attributes
            $content = $this->injectIntoClassAttributes($content, $rules, $totalInjected, $injectedClasses);

            // Inject into ->class([...]) method calls
            $content = $this->injectIntoClassMethod($content, $rules, $totalInjected, $injectedClasses);

            // Inject into @class([...]) directive calls
            $content = $this->injectIntoAtClassDirective($content, $rules, $totalInjected, $injectedClasses);

            file_put_contents($file, $content);
        }

        return [
            'processed' => count($files),
            'injected' => $totalInjected,
        ];
    }

    /**
     * Check if a file contains extractable patterns (__name__ classes __)
     */
    public function hasExtractablePatterns(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);

        // Check for patterns in class="" attributes
        if (preg_match('/class="[^"]*?__([a-zA-Z0-9\-_]+)__.*?__[^"]*?"/', $content)) {
            return true;
        }

        // Check for patterns in ->class([...]) method calls
        if (preg_match('/->class\(\[.*?__([a-zA-Z0-9\-_]+)__.*?__.*?\]\)/s', $content)) {
            return true;
        }

        // Check for patterns in @class([...]) directive calls
        if (preg_match('/@class\(\[.*?__([a-zA-Z0-9\-_]+)__.*?__.*?\]\)/s', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Get all blade files matching the target
     */
    public function getBladeFiles(string $path): array
    {
        // Check if it's a comma-separated list
        if (str_contains($path, ',')) {
            $paths = array_map('trim', explode(',', $path));
            $allFiles = [];

            foreach ($paths as $singlePath) {
                $files = $this->getBladeFiles($singlePath);
                $allFiles = array_merge($allFiles, $files);
            }

            return array_unique($allFiles);
        }

        if (is_file($path)) {
            $this->assertWithinProjectRoot($path);
            if ($this->shouldIgnorePath($path)) {
                return [];
            }

            return [$path];
        }

        if (is_dir($path)) {
            $this->assertWithinProjectRoot($path);
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            $files = [];
            foreach ($rii as $file) {
                if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getPathname())) {
                    $pathname = $file->getPathname();
                    if (! $this->shouldIgnorePath($pathname)) {
                        $files[] = $pathname;
                    }
                }
            }

            return $files;
        }

        // Not a file or directory, treat as search pattern
        return $this->searchBladeFiles($path);
    }

    /**
     * Search for blade files matching a pattern
     */
    protected function searchBladeFiles(string $pattern): array
    {
        $found = [];

        // Add .blade.php if not present
        if (! str_contains($pattern, '.blade.php')) {
            $pattern = '*' . $pattern . '*.blade.php';
        }

        // Convert simple wildcards to regex
        $regexPattern = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';

        $searchPath = $this->config['search_path'] ?? base_path('resources/views');
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchPath));

        foreach ($rii as $file) {
            if ($file->isFile()) {
                $pathname = $file->getPathname();
                $filename = basename($pathname);

                if ($this->shouldIgnorePath($pathname)) {
                    continue;
                }

                if (preg_match($regexPattern, $filename)) {
                    $found[] = $pathname;
                }
            }
        }

        return $found;
    }

    /**
     * Check if a file path should be ignored
     */
    protected function shouldIgnorePath(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->ignoredDirectories as $ignoreDir) {
            $normalizedIgnoreDir = str_replace('\\', '/', $ignoreDir);
            $normalizedIgnoreDir = ltrim($normalizedIgnoreDir, './');

            if (str_contains($normalizedPath, '/' . $normalizedIgnoreDir) ||
                str_starts_with($normalizedPath, $normalizedIgnoreDir) ||
                str_starts_with($normalizedPath, './' . $normalizedIgnoreDir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assert a path does not escape the project root
     */
    protected function assertWithinProjectRoot(string $path): void
    {
        $resolved = realpath($path);

        if ($resolved === false) {
            return;
        }

        if (! str_starts_with($resolved . DIRECTORY_SEPARATOR, $this->projectRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Path '$path' is outside the allowed project directory.");
        }
    }

    /**
     * Generate unique hash for a file path
     */
    protected function getFileHash(string $filepath): string
    {
        return substr(sha1($filepath), 0, $this->hashLength);
    }

    /**
     * Read existing CSS rules from file
     */
    protected function readExistingCssRules(string $cssFile): array
    {
        if (! file_exists($cssFile)) {
            return [];
        }

        $css = file_get_contents($cssFile);
        preg_match_all('/\.([a-zA-Z0-9\-_]+)\s*\{\s*@apply\s+([^;]+);?\s*\}/', $css, $matches, PREG_SET_ORDER);

        $rules = [];
        foreach ($matches as $m) {
            $rules[$m[1]] = trim($m[2]);
        }

        return $rules;
    }

    /**
     * Check if content contains reserved Tailwind classes
     */
    protected function containsReservedClasses(string $content): bool
    {
        foreach ($this->reservedClasses as $reservedClass) {
            if (preg_match('/(?:^|\s)' . preg_quote($reservedClass, '/') . '(?:\s|$)/', $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that @apply content only contains valid characters
     */
    protected function assertValidApplyContent(string $apply, string $name, string $file, string $originalContent = ''): void
    {
        if (preg_match('/[^a-zA-Z0-9\-\/\:\_\.\[\]!\s]/', $apply, $badMatch, PREG_OFFSET_CAPTURE)) {
            $badChar = $badMatch[0][0];
            $offset = $badMatch[0][1];
            $excerpt = substr($apply, max(0, $offset - 20), 40);

            $lineInfo = '';
            if ($originalContent !== '') {
                $pos = strpos($originalContent, $apply);
                if ($pos !== false) {
                    $lineNum = substr_count(substr($originalContent, 0, $pos), "\n") + 1;
                    $lineInfo = ':' . $lineNum;
                }
            }

            throw new RuntimeException(
                "Illegal character in @apply content!\n" .
                "   Class name : $name\n" .
                '   Bad char   : ' . json_encode($badChar) . " (at offset $offset)\n" .
                "   Context    : \"...$excerpt...\"\n" .
                "   Full apply : $apply\n" .
                "   File       : $file$lineInfo\n\n" .
                "   Only Tailwind-valid characters are allowed between __ markers:\n" .
                '   letters, digits, - / : _ . [ ] !'
            );
        }
    }

    /**
     * Extract from class="" attributes
     */
    protected function extractFromClassAttributes(
        string $content,
        string $originalContent,
        string $file,
        array &$newRules,
        array &$existingRules,
        array &$fileSpecificRules,
        array &$classRegistry
    ): string {
        $fileHash = $this->getFileHash($file);
        $iteration = 0;

        do {
            $hasMatch = false;
            $iteration++;

            $newContent = preg_replace_callback(
                '/class="([^"]*?)__([a-zA-Z0-9\-_]+)__(.*?)__([^"]*?)"/',
                function ($matches) use (&$newRules, &$existingRules, &$fileSpecificRules, $file, $fileHash, &$classRegistry, &$hasMatch, $originalContent) {
                    $hasMatch = true;
                    $before = trim($matches[1]);
                    $name = trim($matches[2]);
                    $apply = trim($matches[3]);
                    $after = trim($matches[4]);

                    $this->assertValidApplyContent($apply, $name, $file, $originalContent);

                    if ($this->containsReservedClasses($apply)) {
                        // Return original content without modification
                        return $matches[0];
                    }

                    $cssClass = $this->classPrefix . '-' . $fileHash . '-' . $name;

                    if (isset($classRegistry[$cssClass]) && $classRegistry[$cssClass] !== $apply) {
                        throw new RuntimeException(
                            "Duplicate class name with different content!\n" .
                            "   Class: .$cssClass\n" .
                            "   File: $file\n" .
                            "   First occurrence: @apply {$classRegistry[$cssClass]};\n" .
                            "   Current occurrence: @apply $apply;"
                        );
                    }

                    if (! isset($classRegistry[$cssClass])) {
                        $classRegistry[$cssClass] = $apply;

                        if (! isset($existingRules[$cssClass]) || $existingRules[$cssClass] !== $apply) {
                            $newRules[$cssClass] = $apply;
                            $fileSpecificRules[] = $cssClass;
                        }
                    }

                    $classes = trim($before . ' ' . $cssClass . ' ' . $after);

                    return 'class="' . preg_replace('/\s+/', ' ', $classes) . '"';
                },
                $content
            );

            $content = $newContent;
        } while ($hasMatch && $iteration < $this->maxIterations);

        return $content;
    }

    /**
     * Extract from ->class([...]) method calls
     */
    protected function extractFromClassMethod(
        string $content,
        string $originalContent,
        string $file,
        array &$newRules,
        array &$existingRules,
        array &$fileSpecificRules,
        array &$classRegistry
    ): string {
        $fileHash = $this->getFileHash($file);

        return preg_replace_callback(
            '/->class\(\[(.*?)\]\)/s',
            function ($matches) use (&$newRules, &$existingRules, &$fileSpecificRules, $file, $fileHash, &$classRegistry, $originalContent) {
                $classContent = $matches[1];

                $processedContent = preg_replace_callback(
                    '/([\'"])([^\'"]*)__([a-zA-Z0-9\-_]+)__(.*?)__([^\'"]*)([\'"])/s',
                    function ($quoteMatches) use (&$newRules, &$existingRules, &$fileSpecificRules, $file, $fileHash, &$classRegistry, $originalContent) {
                        $quote = $quoteMatches[1];
                        $before = trim($quoteMatches[2]);
                        $name = trim($quoteMatches[3]);
                        $apply = trim($quoteMatches[4]);
                        $after = trim($quoteMatches[5]);
                        $endQuote = $quoteMatches[6];

                        $this->assertValidApplyContent($apply, $name, $file, $originalContent);

                        if ($this->containsReservedClasses($apply)) {
                            return $quoteMatches[0];
                        }

                        $cssClass = $this->classPrefix . '-' . $fileHash . '-' . $name;

                        if (isset($classRegistry[$cssClass]) && $classRegistry[$cssClass] !== $apply) {
                            throw new RuntimeException(
                                "Duplicate class name with different content in $file"
                            );
                        }

                        if (! isset($classRegistry[$cssClass])) {
                            $classRegistry[$cssClass] = $apply;

                            if (! isset($existingRules[$cssClass]) || $existingRules[$cssClass] !== $apply) {
                                $newRules[$cssClass] = $apply;
                                $fileSpecificRules[] = $cssClass;
                            }
                        }

                        $classes = trim($before . ' ' . $cssClass . ' ' . $after);
                        $cleanClasses = preg_replace('/\s+/', ' ', $classes);

                        return $quote . $cleanClasses . $endQuote;
                    },
                    $classContent
                );

                return '->class([' . $processedContent . '])';
            },
            $content
        );
    }

    /**
     * Extract from @class([...]) directive calls
     */
    protected function extractFromAtClassDirective(
        string $content,
        string $originalContent,
        string $file,
        array &$newRules,
        array &$existingRules,
        array &$fileSpecificRules,
        array &$classRegistry
    ): string {
        $fileHash = $this->getFileHash($file);

        return preg_replace_callback(
            '/@class\(\[(.*?)\]\)/s',
            function ($matches) use (&$newRules, &$existingRules, &$fileSpecificRules, $file, $fileHash, &$classRegistry, $originalContent) {
                $classContent = $matches[1];

                $processedContent = preg_replace_callback(
                    '/([\'"])([^\'"]*)__([a-zA-Z0-9\-_]+)__(.*?)__([^\'"]*)([\'"])(\s*=>\s*[^,\]]+)?/s',
                    function ($quoteMatches) use (&$newRules, &$existingRules, &$fileSpecificRules, $file, $fileHash, &$classRegistry, $originalContent) {
                        $quote = $quoteMatches[1];
                        $before = trim($quoteMatches[2]);
                        $name = trim($quoteMatches[3]);
                        $apply = trim($quoteMatches[4]);
                        $after = trim($quoteMatches[5]);
                        $endQuote = $quoteMatches[6];
                        $condition = $quoteMatches[7] ?? '';

                        $this->assertValidApplyContent($apply, $name, $file, $originalContent);

                        if ($this->containsReservedClasses($apply)) {
                            return $quoteMatches[0];
                        }

                        $cssClass = $this->classPrefix . '-' . $fileHash . '-' . $name;

                        if (isset($classRegistry[$cssClass]) && $classRegistry[$cssClass] !== $apply) {
                            throw new RuntimeException(
                                "Duplicate class name with different content in $file"
                            );
                        }

                        if (! isset($classRegistry[$cssClass])) {
                            $classRegistry[$cssClass] = $apply;

                            if (! isset($existingRules[$cssClass]) || $existingRules[$cssClass] !== $apply) {
                                $newRules[$cssClass] = $apply;
                                $fileSpecificRules[] = $cssClass;
                            }
                        }

                        $classes = trim($before . ' ' . $cssClass . ' ' . $after);
                        $cleanClasses = preg_replace('/\s+/', ' ', $classes);

                        return $quote . $cleanClasses . $endQuote . $condition;
                    },
                    $classContent
                );

                return '@class([' . $processedContent . '])';
            },
            $content
        );
    }

    /**
     * Inject into class="" attributes
     */
    protected function injectIntoClassAttributes(string $content, array $rules, int &$totalInjected, array &$injectedClasses): string
    {
        foreach ($rules as $class => $apply) {
            $content = preg_replace_callback(
                '/class="([^"]*)\b' . preg_quote($class, '/') . '\b([^"]*)"/',
                function ($matches) use ($class, $apply, &$totalInjected, &$injectedClasses) {
                    $before = trim($matches[1]);
                    $after = trim($matches[2]);

                    $prefixPattern = '/^' . $this->classPrefix . '-[a-f0-9]{' . $this->hashLength . '}-/';
                    $marker = '__' . preg_replace($prefixPattern, '', $class) . '__ ' . $apply . ' __';
                    $classes = trim($before . ' ' . $marker . ' ' . $after);
                    $cleanClasses = preg_replace('/\s+/', ' ', $classes);

                    $totalInjected++;
                    $injectedClasses[] = $class;

                    return 'class="' . $cleanClasses . '"';
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Inject into ->class([...]) method calls
     */
    protected function injectIntoClassMethod(string $content, array $rules, int &$totalInjected, array &$injectedClasses): string
    {
        return preg_replace_callback(
            '/->class\(\[(.*?)\]\)/s',
            function ($matches) use ($rules, &$totalInjected, &$injectedClasses) {
                $classContent = $matches[1];

                foreach ($rules as $class => $apply) {
                    $classContent = preg_replace_callback(
                        '/([\'"])([^\'"]*)(\b' . preg_quote($class, '/') . '\b)([^\'"]*)([\'"])/s',
                        function ($quoteMatches) use ($class, $apply, &$totalInjected, &$injectedClasses) {
                            $quote = $quoteMatches[1];
                            $before = trim($quoteMatches[2]);
                            $after = trim($quoteMatches[4]);
                            $endQuote = $quoteMatches[5];

                            $prefixPattern = '/^' . $this->classPrefix . '-[a-f0-9]{' . $this->hashLength . '}-/';
                            $marker = '__' . preg_replace($prefixPattern, '', $class) . '__ ' . $apply . ' __';
                            $classes = trim($before . ' ' . $marker . ' ' . $after);
                            $cleanClasses = preg_replace('/\s+/', ' ', $classes);

                            $totalInjected++;
                            $injectedClasses[] = $class;

                            return $quote . $cleanClasses . $endQuote;
                        },
                        $classContent
                    );
                }

                return '->class([' . $classContent . '])';
            },
            $content
        );
    }

    /**
     * Inject into @class([...]) directive calls
     */
    protected function injectIntoAtClassDirective(string $content, array $rules, int &$totalInjected, array &$injectedClasses): string
    {
        return preg_replace_callback(
            '/@class\(\[(.*?)\]\)/s',
            function ($matches) use ($rules, &$totalInjected, &$injectedClasses) {
                $classContent = $matches[1];

                foreach ($rules as $class => $apply) {
                    $classContent = preg_replace_callback(
                        '/([\'"])([^\'"]*)(\b' . preg_quote($class, '/') . '\b)([^\'"]*)([\'"])(\s*=>\s*[^,\]]+)?/s',
                        function ($quoteMatches) use ($class, $apply, &$totalInjected, &$injectedClasses) {
                            $quote = $quoteMatches[1];
                            $before = trim($quoteMatches[2]);
                            $after = trim($quoteMatches[4]);
                            $endQuote = $quoteMatches[5];
                            $condition = $quoteMatches[6] ?? '';

                            $prefixPattern = '/^' . $this->classPrefix . '-[a-f0-9]{' . $this->hashLength . '}-/';
                            $marker = '__' . preg_replace($prefixPattern, '', $class) . '__ ' . $apply . ' __';
                            $classes = trim($before . ' ' . $marker . ' ' . $after);
                            $cleanClasses = preg_replace('/\s+/', ' ', $classes);

                            $totalInjected++;
                            $injectedClasses[] = $class;

                            return $quote . $cleanClasses . $endQuote . $condition;
                        },
                        $classContent
                    );
                }

                return '@class([' . $classContent . '])';
            },
            $content
        );
    }

    /**
     * Update CSS file with new rules
     */
    protected function updateCssFile(string $cssFile, array $fileRules, array $newRules): void
    {
        $existingCss = file_exists($cssFile) ? file_get_contents($cssFile) : '';
        $newCss = $existingCss;

        foreach ($fileRules as $file => $rules) {
            $newCss .= "\n/* " . $file . " */\n";
            foreach ($rules as $cssClass) {
                $newCss .= '.' . $cssClass . ' { @apply ' . $newRules[$cssClass] . "; }\n";
            }
        }

        file_put_contents($cssFile, $newCss);
    }
}
