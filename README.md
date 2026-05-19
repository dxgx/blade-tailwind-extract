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
- ✅ **Works with `class=""`, `->class([...])`, and `@class([...])`** - Full Blade/Livewire support
- ✅ **Bidirectional** - Extract for production, inject for development
- ✅ **Safe** - Reserved classes like `group` and `peer` are automatically skipped
- ✅ **Collision-free** - File-based hashing prevents class name conflicts
- ✅ **Pattern matching** - Process specific files or entire directories
- ✅ **Configurable** - Customize prefix, hash length, output path, and more

## Installation

Install via Composer:

```bash
composer require dxgx/blade-tailwind-extract
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=blade-tailwind-extract-config
```

## Usage

### Extract Classes (Development → Production)

The `target` parameter accepts multiple formats:

**1. Directory (processes all .blade.php files recursively):**
```bash
php artisan dgtool:blade-tailwind-extract extract ./resources/views
php artisan dgtool:blade-tailwind-extract e ./resources/views/components
```

**2. Single file path:**
```bash
php artisan dgtool:blade-tailwind-extract extract resources/views/livewire/garage/list/item.blade.php
php artisan dgtool:blade-tailwind-extract e components/card.blade.php
```

**3. Pattern matching (searches in configured search_path):**
```bash
php artisan dgtool:blade-tailwind-extract e *preview*
php artisan dgtool:blade-tailwind-extract e *card*.blade.php
php artisan dgtool:blade-tailwind-extract e list-item
```

**4. Multiple targets (comma-separated, can mix patterns and files):**
```bash
php artisan dgtool:blade-tailwind-extract e image-preview.blade.php,card-list.blade.php
php artisan dgtool:blade-tailwind-extract e *preview*,*card*,header.blade.php
```

### Inject Classes (Production → Development)

Restore Tailwind classes for editing (accepts same target formats as extract):

```bash
# Directory
php artisan dgtool:blade-tailwind-extract inject ./resources/views

# Pattern
php artisan dgtool:blade-tailwind-extract r *preview*  # 'r' is short alias for inject

# Specific file
php artisan dgtool:blade-tailwind-extract r components/card.blade.php

# Multiple
php artisan dgtool:blade-tailwind-extract inject *card*,*list*
```

### Workflow

1. **Inject** before editing (restore Tailwind classes to inline format)
2. **Edit** your Blade files normally
3. **Extract** after editing (compact back to short class names)
4. **Commit** both Blade files and CSS file

### Class Marker Syntax

Wrap classes you want to extract with double underscores:

```blade
<div class="__card-wrapper__ flex flex-col gap-2 p-4 __">
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

Configuration file: `config/blade-tailwind-extract.php`

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
php artisan dgtool:blade-tailwind-extract extract ./resources/views --css-file=resources/css/my-custom.css
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

### Handling Renamed/Moved Files

The hash is based on the file path. If you rename or move a Blade file:

1. Inject the file first (restore classes)
2. Move/rename the file
3. Extract again (generates new hash)

## How It Works

1. **Extract Mode:**
   - Scans for `__name__ tailwind classes __` patterns
   - Generates short class names: `{PREFIX}-{HASH}-{NAME}`
   - Writes `@apply` rules to the CSS file
   - Replaces inline classes with short names

2. **Inject Mode:**
   - Reads existing `@apply` rules from CSS file
   - Finds short class names in Blade files
   - Restores original Tailwind class strings

3. **File Hash:**
   - Each file gets a unique 4-character hash (configurable)
   - Prevents class name collisions between files
   - Allows same semantic name (`card-wrapper`) across different files

## Gotchas & Best Practices

- ⚠️ Never manually edit `TW-*` class names in extracted files
- ⚠️ Always inject → edit → extract workflow
- ⚠️ Don't extract `group`, `peer`, or other parent-modifier classes
- ✅ Commit both Blade and CSS files together
- ✅ Run extract before deploying to production
- ✅ Run inject before starting development

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
