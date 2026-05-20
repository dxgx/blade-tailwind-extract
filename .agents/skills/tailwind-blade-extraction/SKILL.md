---
name: tailwind-blade-extraction
description: Expert guidance for Tailwind CSS class extraction patterns in Laravel Blade templates, optimizing Livewire wire payloads through @apply directive
---

# Tailwind Blade Extraction Skill

Expert in Tailwind CSS utility class extraction for Laravel Blade templates, specializing in reducing Livewire component wire transfer sizes through strategic use of the `@apply` directive.

## Core Purpose

This package solves the **Livewire wire payload problem** by extracting verbose Tailwind class strings into short, reusable CSS class names. When rendering lists of Livewire components with long Tailwind class strings, the payload becomes massive because the same classes are repeated for every item. This skill teaches optimal patterns for extraction.

## Extraction Pattern Syntax

### Writing Extractable Classes in Blade

Use the special `__name__ classes __` syntax to mark classes for extraction:

**Supported Patterns:**

1. **Standard `class=""` attributes:**
```blade
<div class="__card-wrapper__ flex flex-col gap-2 p-4 bg-white rounded-lg shadow-md __">
    <!-- Content -->
</div>
```

2. **Livewire `->class([...])` method:**
```blade
<div {{ $attributes->class(['__list-item__ flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 __']) }}>
    <!-- Content -->
</div>
```

3. **Blade `@class([...])` directive:**
```blade
<div @class([
    '__status-badge__ inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium __',
    'bg-green-100 text-green-800' => $active,
    'bg-gray-100 text-gray-800' => !$active,
])>
    Status
</div>
```

### Extraction Rules

- **Name format:** `__descriptive-name__` (uses kebab-case)
- **Closing delimiter:** Always end with `__` before closing quote
- **Whitespace:** Spaces around delimiters are fine
- **Multiple per file:** Each file can have multiple extraction patterns with different names

### What Gets Generated

**Input (Development):**
```blade
<div class="__card__ flex flex-col gap-2 p-4 bg-white rounded-lg __">
```

**Output (Production):**
```blade
<div class="TW-a40f-card">
```

**Generated CSS:**
```css
.TW-a40f-card {
    @apply flex flex-col gap-2 p-4 bg-white rounded-lg;
}
```

**Format:** `{PREFIX}-{HASH}-{NAME}`
- `TW`: Configurable prefix (default)
- `a40f`: 4-character hash derived from file path (prevents collisions)
- `card`: Your descriptive name

## Reserved Classes - CRITICAL

### Never Extract These

**`group` and `peer` classes CANNOT be extracted** because they use parent-child CSS selectors that break when moved to `@apply`:

❌ **Wrong:**
```blade
<div class="__parent__ group hover:bg-gray-100 __">
    <span class="__child__ text-gray-500 group-hover:text-gray-900 __">
```

✅ **Correct:**
```blade
<div class="group hover:bg-gray-100">
    <span class="__child__ text-gray-500 group-hover:text-gray-900 __">
```

**Keep `group`/`peer` inline, extract children:**
- Parent element: Keep `group` or `peer` class inline
- Child elements: Can extract `group-hover:*`, `peer-*` variants

### Reserved Class Behavior

- The package automatically detects and **skips** extractions containing `group` or `peer`
- You'll see a **warning** when attempting to extract these
- Defined in `config/blade-tailwind-extract.php` under `reserved_classes`

## When to Extract vs Keep Inline

### ✅ Extract When:

1. **Repeated in lists/loops** - Same classes on many Livewire components
```blade
@foreach($items as $item)
    <div class="__list-item__ flex items-center justify-between p-4 bg-white border-b __">
        <!-- Payload win: 100 items = massive savings -->
    </div>
@endforeach
```

2. **Complex, stable patterns** - Verbose classes that rarely change
```blade
<div class="__modal-wrapper__ fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 backdrop-blur-sm __">
```

3. **Component consistency** - Shared styles across multiple Blade components
```blade
<!-- components/card.blade.php -->
<div class="__card-base__ rounded-lg shadow-md bg-white p-6 hover:shadow-lg transition-shadow duration-200 __">
```

### ❌ Keep Inline When:

1. **Dynamic or conditional classes** - Frequently changing based on state
```blade
<div class="p-4 {{ $isActive ? 'bg-green-100' : 'bg-gray-100' }}">
```

2. **One-off styles** - Unique to a single element
```blade
<div class="mt-8"><!-- Single use, no payload impact --></div>
```

3. **Contains `group` or `peer`** - Parent selectors break with `@apply`
```blade
<div class="group flex items-center">
    <span class="group-hover:text-blue-600">Link</span>
</div>
```

4. **Rapid prototyping** - Still designing, classes change frequently

## Workflow: Extract and Inject

### Development Mode (Injected)

Work with **inline classes** wrapped in extraction markers:

```blade
<div class="__wrapper__ flex flex-col gap-4 p-6 __">
    <!-- Easy to edit and see all classes -->
</div>
```

**Command:**
```bash
php artisan dgtool:blade-tailwind-extract inject resources/views/components/
```

### Production Mode (Extracted)

Deploy with **short class names**:

```blade
<div class="TW-a40f-wrapper">
    <!-- Minimal payload -->
</div>
```

**Command:**
```bash
php artisan dgtool:blade-tailwind-extract extract resources/views/components/
```

### Best Practice Workflow

1. **Develop with injected classes** - Full Tailwind strings visible
2. **Test with injected classes** - Ensure styles work correctly
3. **Extract before commit/deploy** - Convert to short names
4. **Commit both** - Blade files + generated CSS file together
5. **Inject after pull** - Restore inline classes for editing

## Understanding @apply Directive

### What @apply Does

The `@apply` directive inlines Tailwind utility classes into custom CSS:

```css
.TW-a40f-card {
    @apply flex flex-col gap-2 p-4 bg-white rounded-lg shadow-md;
}
```

At build time, Tailwind processes this into:

```css
.TW-a40f-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background-color: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}
```

### @apply Best Practices

1. **Performance:** Using CSS variables directly can be faster than `@apply` in some contexts (per Context7 docs)
2. **Theme consistency:** `@apply` ensures you're using design tokens from your Tailwind config
3. **Build process:** Requires Tailwind to process the CSS file during builds
4. **Scope:** Works best for extracting repeated patterns, not one-off styles

### Generated CSS File

**Location:** `resources/css/tw-extracted.css` (configurable)

**Include in build:** Make sure this file is imported in your main CSS:

```css
/* app.css */
@import 'tailwindcss';
@import './tw-extracted.css';
```

## File Hashing and Collision Avoidance

### Why File Hashing?

Each Blade file gets a unique hash to prevent class name collisions:

```
resources/views/components/card.blade.php     → TW-a40f-wrapper
resources/views/livewire/list-item.blade.php → TW-b72e-wrapper
```

Same name (`wrapper`) but different files = different generated classes.

### Hash Characteristics

- **Derived from file path** - Consistent for the same file
- **4 characters by default** - Configurable in `config/blade-tailwind-extract.php`
- **Deterministic** - Same file always produces same hash

### Important: File Renaming

**If you rename/move a Blade file:**
1. Inject classes first (restore inline)
2. Move/rename the file
3. Extract again (generates new hash)

Otherwise, your CSS and Blade files will be out of sync.

## Configuration Reference

### Key Config Options

**File:** `config/blade-tailwind-extract.php`

```php
return [
    'css_output_path' => resource_path('css/tw-extracted.css'),
    'class_prefix' => 'TW',
    'hash_length' => 4,
    'search_path' => resource_path('views'),
    'reserved_classes' => ['group', 'peer'],
    'max_iterations' => 10,
];
```

### Customization Examples

**Different prefix:**
```php
'class_prefix' => 'EX', // Generates EX-a40f-card
```

**Longer hash for large projects:**
```php
'hash_length' => 6, // Generates TW-a40f2e-card
```

**Different CSS output:**
```php
'css_output_path' => public_path('css/extracted.css'),
```

## Command Usage Patterns

### Target Formats

1. **Directory (recursive):**
```bash
php artisan dgtool:blade-tailwind-extract extract ./resources/views/components
```

2. **Single file:**
```bash
php artisan dgtool:blade-tailwind-extract extract resources/views/livewire/list-item.blade.php
```

3. **Pattern matching:**
```bash
php artisan dgtool:blade-tailwind-extract extract "*card*"
php artisan dgtool:blade-tailwind-extract extract "*preview*.blade.php"
```

4. **Multiple targets:**
```bash
php artisan dgtool:blade-tailwind-extract extract "card.blade.php,list.blade.php"
php artisan dgtool:blade-tailwind-extract extract "*preview*,*card*"
```

### Command Aliases

- `extract` or `e` - Extract classes (inline → short names)
- `inject` or `r` - Inject classes (short names → inline, "restore")

## Common Pitfalls

### 1. Extracting Group/Peer Classes

❌ **Problem:**
```blade
<div class="__parent__ group flex items-center __">
```

✅ **Solution:** Keep `group` inline
```blade
<div class="group flex items-center">
```

### 2. Forgetting to Import Generated CSS

Make sure `tw-extracted.css` is imported in your build:
```css
@import './tw-extracted.css';
```

### 3. Editing Generated Classes Manually

❌ **Don't manually edit** `TW-*` classes in Blade files
✅ **Always use inject → edit → extract** workflow

### 4. Mismatched Blade and CSS

**Symptoms:** Missing styles, classes not found

**Cause:** Blade file was renamed/moved without re-extracting

**Fix:**
```bash
php artisan dgtool:blade-tailwind-extract inject {file}
# Move or rename file
php artisan dgtool:blade-tailwind-extract extract {file}
```

### 5. Forgetting Closing Delimiter

❌ **Wrong:**
```blade
<div class="__card__ flex flex-col gap-2">
```

✅ **Correct:**
```blade
<div class="__card__ flex flex-col gap-2 __">
```

## Testing Considerations

When writing tests for components using extraction:

1. **Test with extracted classes** - Use production format
2. **Check CSS generation** - Verify `tw-extracted.css` updates
3. **Validate no reserved classes** - Ensure `group`/`peer` stay inline
4. **Test both modes** - Verify extract and inject both work

## Integration with Tailwind Build

### Ensure Tailwind Processes Generated CSS

**tailwind.config.js:**
```javascript
module.exports = {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/css/tw-extracted.css', // Important!
    ],
    // ...
}
```

This ensures Tailwind processes your `@apply` directives during builds.

## Performance Benefits

### Wire Payload Reduction

**Before extraction (100 list items):**
```
Payload size: ~150KB
Classes per item: ~80 characters
Total: 8,000 characters of repeated classes
```

**After extraction (100 list items):**
```
Payload size: ~15KB
Classes per item: ~15 characters
Total: 1,500 characters + one-time CSS
Reduction: ~90%
```

### When Benefits Are Greatest

- **Large lists** - More items = bigger savings
- **Complex components** - More classes = bigger savings
- **Frequent updates** - Livewire wire interactions benefit most

## Quick Reference

### Extract for Production
```bash
php artisan dgtool:blade-tailwind-extract e resources/views/components/
```

### Inject for Development
```bash
php artisan dgtool:blade-tailwind-extract r resources/views/components/
```

### Pattern Syntax
```blade
<div class="__name__ tailwind classes here __">
```

### Reserved Classes
Keep `group` and `peer` inline, never extract them.

### Generated Format
`{PREFIX}-{HASH}-{NAME}` (e.g., `TW-a40f-card`)

### File Hash
Changes if file is renamed/moved - always re-extract after moving.

---

**Remember:** This package is specifically designed to optimize Livewire component payloads. If you're not using Livewire with repeated components, the benefits may be minimal. Focus extraction on list items, table rows, and repeated component patterns for maximum impact.
