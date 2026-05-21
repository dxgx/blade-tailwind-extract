# Agent Instructions: Blade Tailwind Extract Package

A Laravel package (command) that extracts Tailwind CSS classes from Blade templates into CSS `@apply` rules, reducing Livewire wire payload size.

## Quick Reference

### Command Usage
```bash
# Extract (inline → compact)
php artisan dg:blade-tailwind:extract {target}

# Restore (compact → inline)
php artisan dg:blade-tailwind:restore {target}
```

**Target formats:**
- No target: Processes all files in `search_path` (with double confirmation prompts)
- Directory: `./resources/views` (recursive)
- File: `resources/views/components/card.blade.php`
- Pattern: `*preview*`, `*card*.blade.php`
- Multiple: `card.blade.php,list.blade.php` or `*preview*,*card*`

## Architecture

```
src/
├── BladeTailwindExtractServiceProvider.php  # Laravel integration (registers both commands)
├── TailwindExtractorService.php             # Core extraction/injection logic
└── Commands/
    ├── BladeTailwindExtractCommand.php      # Extract command (inline → compact)
    ├── BladeTailwindRestoreCommand.php      # Restore command (compact → inline)
    └── Concerns/
        └── HandlesBulkOperations.php        # Shared trait for file operations
```

### Core Flow

1. **Extract Command** (`dg:blade-tailwind:extract`):
   - Runs PHP syntax check (`php -l`) on all files before processing
   - Finds `__name__ tailwind classes __` patterns in Blade files
   - When no target specified, filters file list to show only files with extractable patterns
   - Generates CSS class: `{PREFIX}-{HASH}-{NAME}` (e.g., `TW-a40f-card-wrapper`)
   - Writes `@apply` rules to CSS file
   - Replaces inline classes with generated name

2. **Restore Command** (`dg:blade-tailwind:restore`):
   - Reads `@apply` rules from CSS file
   - Finds generated class names in Blade files
   - Restores original Tailwind class strings

### Validation & Safety Features

- **PHP Lint Check**: Before extraction, all PHP files are validated using `php -l` to catch syntax errors
- **Smart File Filtering**: When extracting without target, only files with `__name__ classes __` patterns are shown in the confirmation list
- **Double Confirmation**: When processing all files (no target), prompts twice for safety
- **Skip Flag**: `--yy` flag bypasses all confirmations for automated workflows

### Key Design Decisions

- **File-based hashing**: Each Blade file gets a unique 4-char hash (configurable) derived from file path to prevent class name collisions
- **Reserved classes**: `group` and `peer` cannot be extracted (breaks parent-child selectors) and trigger warnings
- **Pattern support**: Supports `class=""`, `->class([...])`, and `@class([...])`
- **Singleton service**: `TailwindExtractorService` registered as singleton in container

## Development Workflow

### When Adding Features
1. Write test in `tests/Feature/`
2. Run `./vendor/bin/phpunit --filter=<test-name>`
3. Implement in `TailwindExtractorService.php` or command
4. Update [README.md](README.md) with new usage patterns

### When Modifying Extraction Logic
- Main methods in `TailwindExtractorService`:
  - `extractFromClassAttributes()` - handles `class=""`
  - `extractFromClassMethod()` - handles `->class([])`
  - `extractFromAtClassDirective()` - handles `@class([])`
  - `hasExtractablePatterns()` - checks if file contains patterns to extract
  - `getBladeFiles()` - public method to retrieve files for a target
- All use regex to find `__name__ classes __` pattern
- Protected by `$maxIterations` config to prevent infinite loops

### When Modifying Command Logic
- Main methods in `BladeTailwindExtractCommand`:
  - `handleExtract()` - processes extraction logic
  - `lintPhpFiles()` - runs `php -l` on files to check syntax (from trait)
  - `confirmBulkOperation()` - handles file filtering and double confirmation (from trait)
  
- Main methods in `BladeTailwindRestoreCommand`:
  - `handleRestore()` - processes restoration logic
  - Uses same trait methods for bulk operations
  
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

See [config/blade-tailwind-extract.php](config/blade-tailwind-extract.php) for:
- `css_output_path` - where CSS file is generated
- `class_prefix` - default `TW`
- `hash_length` - default 4 characters
- `search_path` - default for pattern matching
- `reserved_classes` - classes that cannot be extracted

## External Documentation

- Full usage examples: [README.md](README.md)
- Contributing guidelines: [CONTRIBUTING.md](CONTRIBUTING.md)
- Package versions & dependencies: [composer.json](composer.json)
