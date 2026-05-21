# Optional Target Parameter Implementation

## Summary

The `dg:blade-tailwind-extract` command now supports running without a target parameter. When no target is provided, it will process all `.blade.php` files in the configured `search_path` with double confirmation prompts.

## Changes Made

### 1. Command Signature Updated
- Made `target` parameter optional by adding `?` suffix: `{target?}`
- Updated description to explain behavior when target is omitted

### 2. New Workflow When Target is Omitted
1. Searches for all `.blade.php` files recursively in `search_path` (from config)
2. Shows first confirmation: "Are you sure you want to process X file(s)?"
3. Displays list of files to be processed (max 50 shown, with "...and X more" if exceeded)
4. Shows second confirmation: "Proceed with [extract|inject] operation on these files?"
5. If either confirmation is declined, cancels the operation
6. If both confirmations accepted, processes all files

### 3. New Helper Methods Added
- `findAllBladeFiles($directory)`: Recursively finds all `.blade.php` files, respecting `ignored_directories` config
- `confirmBulkOperation($mode, $files)`: Handles the two-step confirmation with file list display

### 4. Behavior with Target Parameter (Unchanged)
- When target parameter IS provided, operates exactly as before
- No confirmation prompts shown
- Processes specified target immediately

## Usage Examples

### Without Target (New Behavior)
```bash
# Extract from all files with confirmations
php artisan dg:blade-tailwind-extract extract
php artisan dg:blade-tailwind-extract e

# Inject into all files with confirmations  
php artisan dg:blade-tailwind-extract inject
php artisan dg:blade-tailwind-extract r
```

### With Target (Existing Behavior)
```bash
# No confirmations, processes immediately
php artisan dg:blade-tailwind-extract extract resources/views/card.blade.php
php artisan dg:blade-tailwind-extract e *preview*
```

## Testing

Run the test suite to verify:
```bash
composer install  # If dependencies not installed
./vendor/bin/phpunit --filter=CommandTest
```

Tests cover:
- ✅ Command with specific target (existing behavior)
- ✅ Cancellation on first confirmation prompt
- ✅ Cancellation on second confirmation prompt
- ✅ Successful operation with both confirmations accepted
- ✅ File list display in confirmation
- ✅ Works with both extract and inject modes
- ✅ Handles empty search paths gracefully

## Configuration

The feature respects existing configuration:
- `search_path` - Directory to search when no target provided
- `ignored_directories` - Directories to skip during file search

## Files Modified

1. `/src/Commands/BladeTailwindExtractCommand.php`
   - Made target optional
   - Added confirmation flow for targetless execution
   - Added file discovery and confirmation helper methods

2. `/tests/Feature/CommandTest.php` (new)
   - Comprehensive test coverage for new functionality
