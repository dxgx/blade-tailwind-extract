# Blade Tailwind Extract - Tailwind Class Extractor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dxgx/blade-tailwind-extract.svg?style=flat-square)](https://packagist.org/packages/dxgx/blade-tailwind-extract)
[![Total Downloads](https://img.shields.io/packagist/dt/dxgx/blade-tailwind-extract.svg?style=flat-square)](https://packagist.org/packages/dxgx/blade-tailwind-extract)
[![License](https://img.shields.io/packagist/l/dxgx/blade-tailwind-extract.svg?style=flat-square)](https://packagist.org/packages/dxgx/blade-tailwind-extract)

A Laravel package that dramatically reduces Livewire component wire transfer sizes by extracting long Tailwind CSS class strings into short, reusable CSS class names. Perfect for applications with large lists of Livewire components.

## The Problem

When using Livewire with Tailwind CSS, long class strings in Blade templates get transferred over the wire on every component update:

```blade
<div class="flex flex-col gap-2 p-4 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
    <!-- Large payload repeated for every list item -->
</div>
```

With 100 items in a list, this verbose markup significantly impacts performance.

## The Solution

Blade Tailwind Extract extracts your Tailwind classes into a CSS file using `@apply`, replacing them with short class names:

**Development Mode (Injected):**
```blade
<div class="__card-wrapper__ flex flex-col gap-2 p-4 bg-white rounded-lg shadow-md __">
    <!-- Easy to edit inline -->
</div>
```

**Production Mode (Extracted):**
```blade
<div class="TW-a40f-card-wrapper">
    <!-- Minimal wire payload -->
</div>
```

**Generated CSS:**
```css
.TW-a40f-card-wrapper {
    @apply flex flex-col gap-2 p-4 bg-white rounded-lg shadow-md;
}
```

The `a40f` hash is derived from the file path, ensuring no conflicts between files.

## Features

- ✅ **Reduces Livewire wire payload** - Short class names instead of verbose Tailwind strings
- ✅ **Automated class identification** - Wrap command finds and marks repeated class lists
- ✅ **Intelligent deduplication** - Identical class lists share the same wrapper name
- ✅ **Works with `class=""`, `->class([...])`, and `@class([...])`** - Full Blade/Livewire support
- ✅ **Bidirectional** - Extract for production, restore for development
- ✅ **Safe** - Reserved classes like `group` and `peer` are automatically skipped
- ✅ **Collision-free** - File-based hashing prevents class name conflicts
- ✅ **Pattern matching** - Process specific files or entire directories
- ✅ **Configurable** - Customize prefix, hash length, output path, and more

## Installation

Install via Composer as a dev dependency (this is a development tool, not needed in production):

```bash
composer require dxgx/blade-tailwind-extract --dev
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=blade-tailwind-extract-config
```

## Usage

### Quick Start: 3-Step Workflow

1. **Wrap** - Automatically identify and mark class lists with semantic names
2. **Extract** - Convert marked classes into compact CSS
3. **Restore** - Bring back inline classes for editing

### Wrap Classes (Preparation Step)

Before extraction, use the wrap command to automatically identify and mark repeated Tailwind class lists with semantic wrapper names. This helps you see which classes will be extracted and ensures consistent naming for identical class lists.

```bash
# Wrap classes in a specific file
php artisan dg:blade-tailwind:wrap resources/views/components/card.blade.php

# Wrap classes in an entire directory (recursive)
php artisan dg:blade-tailwind:wrap ./resources/views/livewire

# Wrap classes using a pattern
php artisan dg:blade-tailwind:wrap "*preview*"
php artisan dg:blade-tailwind:wrap "*card*.blade.php"

# Wrap multiple files or patterns (comma-separated)
php artisan dg:blade-tailwind:wrap "card.blade.php,list.blade.php"
php artisan dg:blade-tailwind:wrap "*preview*,*card*"

# Wrap all files in search_path (with confirmation prompts)
php artisan dg:blade-tailwind:wrap

# Skip confirmations for automated workflows
php artisan dg:blade-tailwind:wrap --yy

# Preview changes without modifying files
php artisan dg:blade-tailwind:wrap "*preview*" --dry-run

# Customize minimum class count (default: 3)
php artisan dg:blade-tailwind:wrap components/card.blade.php --min=4

# Skip class lists containing specific prefix (default: TW)
php artisan dg:blade-tailwind:wrap components/card.blade.php --skip-prefix=CUSTOM
```

**What it does:**
- Scans for class lists with 3+ classes (configurable via `--min`)
- Automatically deduplicates: identical class lists get the same wrapper name
- Generates semantic names: `__adjective-noun-number__` (e.g., `__happy-cat-1__`)
- Skips patterns that should never be extracted: `__`, `material-symbols-outlined`, `TW-`
- Shows summary of changes with occurrence counts

**Before wrapping:**
```blade
<div class="flex items-center justify-between gap-4 p-4">Item 1</div>
<div class="flex items-center justify-between gap-4 p-4">Item 2</div>
<div class="bg-blue-500 text-white rounded p-2">Button</div>
```

**After wrapping:**
```blade
<div class="__happy-cat-1__ flex items-center justify-between gap-4 p-4 __">Item 1</div>
<div class="__happy-cat-1__ flex items-center justify-between gap-4 p-4 __">Item 2</div>
<div class="__quick-fox-2__ bg-blue-500 text-white rounded p-2 __">Button</div>
```

Notice how identical class lists share the same wrapper name (`happy-cat-1`), while different lists get unique names.

### Extract Classes (Development → Production)

The `target` parameter accepts multiple formats:

**0. No target (processes all files in search_path with confirmation):**
```bash
# Prompts for confirmation before processing all .blade.php files
php artisan dg:blade-tailwind:extract

# Shows:
# 1. First confirmation: "Are you sure you want to process X file(s)?"
# 2. List of files to be processed (max 50 shown)
# 3. Second confirmation: "Proceed with extract operation?"
# Cancel at any prompt to abort
```

**1. Directory (processes all .blade.php files recursively):**
```bash
php artisan dg:blade-tailwind:extract ./resources/views
php artisan dg:blade-tailwind:extract ./resources/views/components
```

**2. Single file path:**
```bash
php artisan dg:blade-tailwind:extract resources/views/livewire/garage/list/item.blade.php
php artisan dg:blade-tailwind:extract components/card.blade.php
```

**3. Pattern matching (searches in configured search_path):**
```bash
php artisan dg:blade-tailwind:extract *preview*
php artisan dg:blade-tailwind:extract *card*.blade.php
php artisan dg:blade-tailwind:extract list-item
```

**4. Multiple targets (comma-separated, can mix patterns and files):**
```bash
php artisan dg:blade-tailwind:extract image-preview.blade.php,card-list.blade.php
php artisan dg:blade-tailwind:extract *preview*,*card*,header.blade.php
```

### Restore Classes (Production → Development)

Restore Tailwind classes for editing (accepts same target formats as extract):

```bash
# No target (with confirmation prompts)
php artisan dg:blade-tailwind:restore

# Directory
php artisan dg:blade-tailwind:restore ./resources/views

# Pattern
php artisan dg:blade-tailwind:restore *preview*

# Specific file
php artisan dg:blade-tailwind:restore components/card.blade.php

# Multiple
php artisan dg:blade-tailwind:restore *card*,*list*
```

### Workflow

**Option A: Automated (New Files)**
1. **Wrap** your Blade file to identify and mark repeated class lists
2. **Extract** marked classes to generate compact CSS
3. **Commit** both Blade files and CSS file

**Option B: Manual (Existing Workflow)**
1. Manually add `__name__ classes __` markers around classes you want to extract
2. **Extract** to generate compact CSS
3. **Commit** both Blade files and CSS file

**Option C: Editing Existing Extracted Files**
1. **Restore** before editing (restore Tailwind classes to inline format)
2. **Edit** your Blade files normally
3. **Extract** after editing (compact back to short class names)
4. **Commit** both Blade files and CSS file

You can add markers manually or use the `wrap` command to add them automatically.

**Manual approach:**
Wrap classes you want to extract with double underscores:

```blade
<div class="__card-wrapper__ flex flex-col gap-2 p-4 __">
    <!-- Content -->
</div>
```

**Automated approach:**
Use the wrap command to automatically identify and mark class lists:

```bash
php artisan dg:blade-tailwind:wrap your.view.namelass="__card-wrapper__ flex flex-col gap-2 p-4 __">
    <!-- Content -->
</div>
```

After extraction:

```blade
<div class="TW-a40f-card-wrapper">
    <!-- Content -->
</div>
```

**Important:** The `group` and `peer` Tailwind classes **cannot** be extracted (they break parent-child selectors). The tool will warn and skip them automatically.

## Configuration

Configuration file: `config/dg-blade-tailwind-extract.php`

```php
return [
    // CSS output file path
    'css_output_path' => resource_path('css/tw-extracted.css'),

    // Class prefix (default: TW)
    'class_prefix' => 'TW',

    // Hash length for file-based collision avoidance (default: 4)
    'hash_length' => 4,

    // Default search path when using pattern matching
    'search_path' => resource_path('views'),

    // Directories to ignore during file scanning
    'ignored_directories' => [
        './vendor/',
        'vendor/',
        './node_modules/',
        'node_modules/',
    ],

    // Reserved Tailwind classes that cannot be extracted
    'reserved_classes' => [
        'group',
        'peer',
    ],

    // Safety limit for extraction loops
    'max_iterations' => 10,
];
```

## Advanced Usage

### Custom CSS Output Path

```bash
php artisan dg:blade-tailwind:extract ./resources/views --css-file=resources/css/my-custom.css
```

### Working with Livewire Components

The tool works seamlessly with Livewire's `->class([...])` method and Blade's `@class([...])` directive:

**Before Extraction:**
```blade
<div @class([
    '__item-wrapper__ flex gap-2 p-4 __',
    'bg-green-50' => $selected,
])>
```

**After Extraction:**
```blade
<div @class([
    'TW-a40f-item-wrapper',
    'bg-green-50' => $selected,
])>
```
Wrap Mode (Optional Preparation):**
   - Scans for class lists with minimum number of classes (default: 3)
   - Automatically deduplicates identical class lists
   - Generates semantic wrapper names: `__adjective-noun-number__`
   - Marks class lists: `__name__ classes __`
   - Skips protected patterns (material-symbols-outlined, TW-, etc.)
   - Shows summary with occurrence counts

2. **Extract Mode:**
   - Scans for `__name__ tailwind classes __` patterns
   - Generates short class names: `{PREFIX}-{HASH}-{NAME}`
   - Writes `@apply` rules to the CSS file
   - Replaces inline classes with short names

3. **Restore Mode:**
   - Reads existing `@apply` rules from CSS file
   - Finds short class names in Blade files
   - Restores original Tailwind class strings

4. **Extract Mode:**
   - Scans for `__name__ tailwind classes __` patterns
   - Generates short class names: `{PREFIX}-{HASH}-{NAME}`
   - Writes `@apply` rules to the CSS file
   - Replaces inline classes with short names

2. **Restore Mode:**
   - Reads existing `@apply` rules from CSS file
   - Finds short class names in Blade files
   - Restores original Tailwind class strings

3. **File Hash:**
   - Each file gets a unique 4-character hash (configurable)
   - Prevents class name collisions between files
   - Allows same semantic name (`card-wrapper`) across different files

## Gotchas & Best Practices

- ⚠️ Never manually edit `TW-*` class names in extracted files
- ⚠️ Always restore → edit → extract workflow
- ⚠️ Don't extract `group`, `peer`, or other parent-modifier classes
- ✅ Commit both Blade and CSS files together
- ✅ Run extract before deploying to production
- ✅ Run restore before starting development

## Testing

```bash
composer test
```

## Security

If you discover any security-related issues, please email packages@dxgx.dev instead of using the issue tracker.

## Credits

- [dxgx](https://github.com/dxgx)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.
