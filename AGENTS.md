# Agent Instructions: Blade Tailwind Extract Package

A Laravel package (command) that extracts Tailwind CSS classes from Blade templates into CSS `@apply` rules, reducing Livewire wire payload size.

## Quick Reference

### Command Usage
```bash
# Wrap (automated marker insertion)
php artisan dg:blade-tailwind:wrap {target} [--min=3] [--skip-prefix=TW] [--dry-run] [--yy]

# Extract (inline → compact)
php artisan dg:blade-tailwind:extract {target}

# Restore (compact → inline)
php artisan dg:blade-tailwind:restore {target}
```

**All commands share the same target format:**
- No target: Processes all files in `search_path` (with double confirmation prompts)
- Directory: `./resources/views` (recursive)
- File: `resources/views/components/card.blade.php`
- Pattern: `*preview*`, `*card*.blade.php`
- Multiple: `card.blade.php,list.blade.php` or `*preview*,*card*`

**Wrap command options:**
- `--min=3`: Minimum classes to trigger wrapping (default: 3)
- `--skip-prefix=TW`: Skip class lists with this prefix
- `--dry-run`: Preview without modifying files
- `--yy`: Skip all confirmations

**Extract/Restore target formats:**
- No target: Processes all files in `search_path` (with double confirmation prompts)
- Directory: `./resources/views` (recursive)
- File: `resources/views/components/card.blade.php`
- Pattern: `*preview*`, `*card*.blade.php`
- Multiple: `card.blade.php,list.blade.php` or `*preview*,*card*`

## Architecture

```
src/
├── BladeTailwindExtractServiceProvider.php  # Laravel integration (registers all commands)
├── TailwindExtractorService.php             # Core extraction/injection logic
└── Commands/
    ├── BladeTailwindWrapCommand.php         # Wrap command (automated marker insertion)
    ├── BladeTailwindExtractCommand.php      # Extract command (inline → compact)
    ├── BladeTailwindRestoreCommand.php      # Restore command (compact → inline)
    └── Concerns/
        └── HandlesBulkOperations.php        # Shared trait for file operations
```

### Core Flow

1. **Wrap Command** (`dg:blade-tailwind:wrap`) - Optional Preparation:
   - Accepts same target formats as extract/restore: file, directory, pattern, multiple, or all files
   - Supports `--yy` flag to skip all confirmations for bulk operations
   - When no target specified, processes all files in `search_path` with double confirmation
   - Uses `TailwindExtractorService::getBladeFiles()` for pattern matching
   - Scans for class lists with minimum number of classes (default: 3)
   - Automatically deduplicates: identical class lists get same wrapper name
   - Generates semantic wrapper names: `__adjective-noun-number__` (e.g., `__happy-cat-1__`)
   - Marks class lists: `__name__ classes __`
   - Skips protected patterns: `__`, `material-symbols-outlined`, `TW-`
   - Shows summary with wrapper names and occurrence counts
   - **Supported patterns**:
     - Static `class=""` attributes
     - Livewire `wire:class=""` attributes
     - Alpine.js `:class=""` with static strings
     - Alpine.js `:class=""` with ternary expressions (both branches wrapped independently)
     - Alpine.js `x-bind:class=""` attributes
     - Blade `@class([])` directive with simple arrays
     - Blade `@class([])` directive with conditional arrays (all strings wrapped independently)

2. **Extract Command** (`dg:blade-tailwind:extract`):
   - Runs PHP syntax check (`php -l`) on all files before processing
   - Finds `__name__ tailwind classes __` patterns in Blade files
   - When no target specified, filters file list to show only files with extractable patterns
   - Generates CSS class: `{PREFIX}-{HASH}-{NAME}` (e.g., `TW-a40f-card-wrapper`)
   - Writes `@apply` rules to CSS file
   - Replaces inline classes with generated name
   - **Skips reserved classes**: Patterns containing `group`, `group/`, or `peer` are skipped
   - **Reports skipped patterns**: Displays informative warnings for skipped patterns with reasons

3. **Restore Command** (`dg:blade-tailwind:restore`):
   - Reads `@apply` rules from CSS file
   - Finds generated class names in Blade files
   - Restores original Tailwind class strings
   - **Automatic CSS Cleanup**: Removes restored CSS rules from the output file
   - Tracks all restored class names during injection
   - Cleans up empty file comments and excessive whitespace

### Validation & Safety Features

- **PHP Lint Check**: Before extraction, all PHP files are validated using `php -l` to catch syntax errors
- **Smart File Filtering**: When extracting without target, only files with `__name__ classes __` patterns are shown in the confirmation list
- **Double Confirmation**: When processing all files (no target) in any command (wrap, extract, restore), prompts twice for safety
- **Skip Flag**: `--yy` flag bypasses all confirmations for automated workflows
- **Dry Run**: Wrap command supports `--dry-run` to preview changes before applying

### Key Design Decisions

- **Wrap command deduplication**: Uses normalized whitespace comparison to match identical class lists, ensuring same wrapper names for duplicates
- **Semantic wrapper names**: Format `adjective-noun-counter` (e.g., `happy-cat-1`) for human-readable markers
- **Protected patterns in wrap**: Never wraps class lists containing `__`, `material-symbols-outlined`, or `TW-` prefix
- **File-based hashing**: Each Blade file gets a unique 4-char hash (configurable) derived from file path to prevent class name collisions
- **Reserved classes**: `group`, `group/`, and `peer` cannot be extracted (breaks parent-child selectors) and trigger informative warnings
- **Pattern support**: Comprehensive support for `class=""`, `wire:class=""`, `:class` (static & ternary), `x-bind:class`, `@class([])` (simple & conditional)
- **Pattern processing order**: Complex patterns (`@class` conditionals, `:class` ternaries) processed before simple patterns to prevent interference
- **Ternary branch wrapping**: Both branches of ternary expressions in `:class` are wrapped independently if they meet threshold
- **Conditional array wrapping**: All strings in `@class([...])` conditional arrays are wrapped independently if they meet threshold
- **Singleton service**: `TailwindExtractorService` registered as singleton in container
- **Skipped pattern deduplication**: Multiple iterations of the same skipped pattern are deduplicated before reporting

## Development Workflow

### When Adding Features
1. Write test in `tests/Feature/`
2. Run `./vendor/bin/phpunit --filter=<test-name>`
3. Implement in `TailwindExtractorService.php` or command
4. Update [README.md](README.md) with new usage patterns

### When Modifying Extraction Logic
- Main methods in `TailwindExtractorService`:
  - `extract()` - main extraction method, returns array with `processed`, `new_rules`, and `skipped_patterns`
  - `extractFromClassAttributes()` - handles `class=""`, accepts `&$skippedPatterns` parameter
  - `extractFromClassMethod()` - handles `->class([])`, accepts `&$skippedPatterns` parameter
  - `extractFromAtClassDirective()` - handles `@class([])`, accepts `&$skippedPatterns` parameter
  - `containsReservedClasses()` - checks for reserved classes (group, group/, peer), sets `$reason` by reference
  - `hasExtractablePatterns()` - checks if file contains patterns to extract
  - `getBladeFiles()` - public method to retrieve files for a target
  - `inject()` - restores classes from CSS back to Blade files, returns restored class names
  - `removeRestoredClassesFromCss()` - removes restored CSS rules from output file
- All extraction methods use regex to find `__name__ classes __` pattern
- Protected by `$maxIterations` config to prevent infinite loops
- Skipped patterns are deduplicated by file+name combination before returning

### When Modifying Command Logic
- Main methods in `BladeTailwindWrapCommand`:
  - `handle()` - main command execution with bulk operation support
  - `processFile()` - processes a single Blade file
  - `processClassAttributes()` - orchestrates pattern processing in optimal order
  - `processAtClassConditionals()` - processes all strings within `@class([...])` arrays
  - `processClassTernary()` - processes `:class` ternary expressions and simple bindings
  - `processClassTernaryMatch()` - helper method to process a single `:class` attribute match
  - `wrapIfNeeded()` - determines if class list should be wrapped
  - `generateWrapperName()` - creates semantic wrapper names
  - `showChangeSummary()` - displays grouped results
  - `resolveViewPath()` - (deprecated) kept for backward compatibility
  - Uses `HandlesBulkOperations` trait for file discovery and confirmations
  - Uses `TailwindExtractorService::getBladeFiles()` for pattern matching
  - **Processing order**:
    1. Pattern 3: `@class([...])` conditionals (all strings in array)
    2. Pattern 4: `:class` ternary expressions and simple bindings
    3. Pattern 1: Static `class` and `wire:class` attributes
    4. Pattern 2: Simple `@class([...])` arrays (backward compatibility)
  - Properties:
    - `$adjectives` and `$nouns` - pools for semantic name generation
    - `$classListMap` - tracks class lists to wrapper names (for deduplication)
    - `$changeLog` - records all changes for summary
    - `$neverWrapPatterns` - patterns to never wrap (`__`, `material-symbols-outlined`, `TW-`)
  
- Main methods in `BladeTailwindExtractCommand`:
  - `handleExtract()` - processes extraction logic
  - `lintPhpFiles()` - runs `php -l` on files to check syntax (from trait)
  - `confirmBulkOperation()` - handles file filtering and double confirmation (from trait)
  
- Main methods in `BladeTailwindRestoreCommand`:
  - `handleRestore()` - processes restoration logic and automatically removes restored CSS rules
  - Uses same trait methods for bulk operations
  - Calls `removeRestoredClassesFromCss()` after successful restoration
  
- **Shared trait** (`HandlesBulkOperations`):
  - `findAllBladeFiles()` - recursively finds .blade.php files
  - `confirmBulkOperation()` - shows file list and double confirmation
  - `lintPhpFiles()` - validates PHP syntax
  
- File filtering uses `hasExtractablePatterns()` for extract command
- **PHP syntax must be valid** - all files are linted before extraction; errors will halt the process
2. **Never extract `group` or `peer` classes** - they're in `reserved_classes` config
3. **File renaming breaks hashes** - user must inject → move → extract
4. **Generated classes (`TW-*`) are sacred** - never manually edit
5. **CSS and Blade must sync** - both files should be committed together
6. **Pattern filtering** - bulk extraction only shows/processes files with `__name__ classes __` patterns
- Document changes in README's Configuration section
- Add test coverage for new config values

## Critical Constraints

1. **Never extract `group` or `peer` classes** - they're in `reserved_classes` config
2. **File renaming breaks hashes** - user must restore → move → extract
3. **Generated classes (`TW-*`) are sacred** - never manually edit
4. **CSS and Blade must sync** - both files should be committed together

## Configuration Reference

See [config/dg-blade-tailwind-extract.php](config/dg-blade-tailwind-extract.php) for:
- `css_output_path` - where CSS file is generated
- `class_prefix` - default `TW`
- `hash_length` - default 4 characters
- `search_path` - default for pattern matching
- `reserved_classes` - classes that cannot be extracted

## External Documentation

- Full usage examples: [README.md](README.md)
- Contributing guidelines: [CONTRIBUTING.md](CONTRIBUTING.md)
- Package versions & dependencies: [composer.json](composer.json)
