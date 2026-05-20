# Agent Instructions: Blade Tailwind Extract Package

A Laravel package (command) that extracts Tailwind CSS classes from Blade templates into CSS `@apply` rules, reducing Livewire wire payload size.

## Quick Reference

### Testing
```bash
./vendor/bin/pest
```
**Critical:** Use `./vendor/bin/pest` directly, NOT `php artisan test` or `composer test`.

### Command Usage
```bash
# Extract (inline → compact)
php artisan dg:blade-tailwind-extract extract {target}
php artisan dg:blade-tailwind-extract e {target}  # alias
php artisan dg:blade-tailwind-extract e           # no target = all files with confirmation

# Inject (compact → inline)
php artisan dg:blade-tailwind-extract inject {target}
php artisan dg:blade-tailwind-extract r {target}  # alias (restore)
php artisan dg:blade-tailwind-extract r           # no target = all files with confirmation
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
├── BladeTailwindExtractServiceProvider.php  # Laravel integration
├── TailwindExtractorService.php             # Core extraction/injection logic
└── Commands/
    └── BladeTailwindExtractCommand.php      # Artisan command handler
```

### Core Flow

1. **Extract Mode** (`extract`/`e`):
   - Finds `__name__ tailwind classes __` patterns in Blade files
   - Generates CSS class: `{PREFIX}-{HASH}-{NAME}` (e.g., `TW-a40f-card-wrapper`)
   - Writes `@apply` rules to CSS file
   - Replaces inline classes with generated name

2. **Inject Mode** (`inject`/`r`):
   - Reads `@apply` rules from CSS file
   - Finds generated class names in Blade files
   - Restores original Tailwind class strings

### Key Design Decisions

- **File-based hashing**: Each Blade file gets a unique 4-char hash (configurable) derived from file path to prevent class name collisions
- **Reserved classes**: `group` and `peer` cannot be extracted (breaks parent-child selectors) and trigger warnings
- **Pattern support**: Supports `class=""`, `->class([...])`, and `@class([...])`
- **Singleton service**: `TailwindExtractorService` registered as singleton in container

## Development Workflow

### When Adding Features
1. Write test in `tests/Feature/`
2. Run `./vendor/bin/pest --filter=<test-name>`
3. Implement in `TailwindExtractorService.php` or command
4. Update [README.md](README.md) with new usage patterns

### When Modifying Extraction Logic
- Main methods in `TailwindExtractorService`:
  - `extractFromClassAttributes()` - handles `class=""`
  - `extractFromClassMethod()` - handles `->class([])`
  - `extractFromAtClassDirective()` - handles `@class([])`
- All use regex to find `__name__ classes __` pattern
- Protected by `$maxIterations` config to prevent infinite loops

### When Updating Configuration
- Edit `config/blade-tailwind-extract.php`
- Document changes in README's Configuration section
- Add test coverage for new config values

## Critical Constraints

1. **Never extract `group` or `peer` classes** - they're in `reserved_classes` config
2. **File renaming breaks hashes** - user must inject → move → extract
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
