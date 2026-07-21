# Changelog

All notable changes to `blade-tailwind-extract` will be documented in this file.

## 2.5.3 - 2026-07-21

### Changed
- Updated README with "Used In Production" section listing deadsimpleapps.com, tvfilmai.lt, flyfiles.io, advertgates.eu

## 2.5.0 - 2026-06-05

### Added
- **Automatic reserved class separation**: `group*` and `peer*` classes are now automatically moved outside wrapper markers and extraction instead of skipping the entire pattern, preventing CSS parent-child selector breakage
- **Comprehensive reserved class detection**: Detects all variants including `group`, `group/`, `group-*:`, `group-*/`, `peer`, `peer/`, `peer-*:`, `peer-*/`
- **Reserved class moving feedback**: Wrap command summary now displays count of moved reserved classes with cyan highlighting
- 25 new comprehensive tests covering reserved class moving behavior:
  - `ReservedClassesMovingTest`: 12 tests validating extraction with automatic separation
  - `WrapReservedClassesTest`: 13 tests validating wrap command with automatic separation
- `isReservedClass()` method: Checks if a class matches any reserved pattern
- `separateReservedClasses()` method: Separates classes into extractable and reserved groups

### Changed
- **Wrap command**: All pattern processing methods (`wrapIfNeeded()`, `processAtClassConditionals()`, `processClassTernary()`) now separate reserved classes before checking minimum threshold
- **Extract command**: All extraction methods (`extractFromClassAttributes()`, `extractFromClassMethod()`, `extractFromAtClassDirective()`) now use separation logic instead of skip logic
- **CSS output**: Only extractable classes appear in `@apply` rules; reserved classes remain inline after generated class name
- **Pattern threshold**: Minimum class count now checks extractable classes only, ignoring reserved classes
- **Order preservation**: Reserved classes maintain their original order when moved outside wrappers

### Fixed
- **SkippedPatternsTest**: Updated 8 tests to reflect new behavior (reserved classes now extracted with separation instead of skipped entirely)
- **CommandTest**: Fixed 4 tests with incorrect config key (`blade-tailwind-extract.search_path` → `dg-blade-tailwind-extract.search_path`) and confirmation expectation types

### Technical Details
- Reserved classes are parsed and separated during both wrap and extract operations
- Deduplication in wrap command now based on extractable classes only (reserved classes don't affect wrapper name assignment)
- Empty extractable class lists are still skipped with informative reason: "only contains reserved classes"
- CSS file comments may contain reserved class names in file paths (e.g., `group-hover.blade.php`), so tests now validate only rule bodies

## 2.4.1 - 2026-06-04

### Fixed
- **Critical prefix class name restoration bug**: Fixed restore operation to process class names sorted by length (longest first) to prevent partial replacements when one class name is a prefix of another (e.g., `TW-242f-card` and `TW-242f-card-shadow` now restore correctly without corruption)
- **Blade variable interpolation wrapping bug**: Wrap command now correctly skips class attributes containing Blade variable interpolation (`{{ }}`) to preserve dynamic behavior
- All three injection methods (`injectIntoClassAttributes`, `injectIntoClassMethod`, `injectIntoAtClassDirective`) now use `uksort()` to sort rules by class name length before processing

### Added
- **PrefixClassNameRestoreTest**: 6 comprehensive tests covering prefix restoration scenarios (basic pairs, multi-level nesting, all three patterns, mixed CSS order, multiple occurrences)
- **DynamicClassAttributesTest**: 6 tests verifying wrap/extract correctly handle dynamic Blade interpolation in `class=""`, `wire:class=""`, `:class`, `@class[]` patterns
- **MixedDynamicExtractedClassesTest**: 5 tests for complex scenarios mixing static extracted classes (TW-*) with dynamic `{{ }}` interpolation
- **IntegrationMultiDirectoryTest**: 4 end-to-end tests covering full wrap→extract→restore workflow across multiple directories with various patterns
- Total of 21 new tests with 104 assertions added to ensure reliability

### Technical Details
- Added Blade interpolation detection (`{{ }}` check) in `wrapIfNeeded()`, `processAtClassConditionals()`, and `processClassTernary()` methods
- Longest-first sorting prevents regex partial matches in class name restoration
- Test coverage now includes file hash consistency verification across bulk and individual operations

## 2.4.0 - 2026-06-02

### Fixed
- **Wrap command now correctly handles arbitrary values with quotes**: Fixed regex patterns for static `class=""` and `@class([...])` attributes to properly handle Tailwind arbitrary values containing quotes (e.g., `after:content-['']`, `before:content-[""]`)
- Static class attributes and `@class` directives now use separate patterns for double-quoted and single-quoted attributes to prevent early quote matching
- Closing `__` marker now correctly appears at the end of class lists instead of breaking inside arbitrary values

### Added
- Test coverage for wrapping class lists with arbitrary values containing quotes
- Vendor folder exclusions to optimize disk space

## 2.3.0 - 2026-05-31

### Added
- **Comprehensive Alpine.js `:class` ternary expression support**: Wrap command now processes both branches of ternary expressions independently (e.g., `:class="condition ? 'classes-a' : 'classes-b'"`)
- **Full `@class` conditional array support**: All conditional strings in `@class([...])` arrays are now processed, not just the first one
- **Simple `:class` static binding support**: Static class strings in `:class` attributes are now wrapped alongside ternary expressions
- **`x-bind:class` support**: Explicit Alpine binding syntax now fully supported
- **Smart already-wrapped detection**: All patterns now skip already-wrapped class lists to prevent double-wrapping
- 6 new comprehensive tests for ternary expressions and conditional arrays
- Detailed pattern behavior documentation in README with 8 example patterns

### Changed
- Pattern processing order optimized: `@class` conditionals and `:class` ternaries are processed before simple patterns
- Static `class` and `wire:class` patterns now explicitly exclude dynamic `:class` patterns to prevent conflicts
- Double-quoted and single-quoted `:class` attributes are now processed separately for proper nested quote handling

### Technical Details
- New `processAtClassConditionals()` method for handling all strings within `@class([...])` arrays
- New `processClassTernary()` and `processClassTernaryMatch()` methods for ternary expression processing
- Enhanced regex patterns to properly handle nested quotes in attribute values
- Pattern matching now respects minimum class threshold for all conditional and ternary branches

## 2.2.3 - 2026-05-31

### Fixed
- Enhanced reserved class detection to catch `group-hover:`, `peer-focus:`, `group-active:`, `peer-checked:`, and all other dash-colon variants (e.g., `group-*:`, `peer-*:`) that break parent-child/sibling relationships when extracted

### Added
- 5 new comprehensive tests for reserved class dash-colon variant detection
- Detection pattern now covers all Tailwind pseudo-class modifiers for `group` and `peer` classes

## 2.2.2 - 2026-05-31

### Added
- Informative warnings when extraction skips patterns containing reserved classes
- Support for `group/` variant detection (e.g., `group/item`, `group/card`)
- Comprehensive test suite for skipped patterns (3 new tests)
- Detailed skip reason reporting with file path, pattern name, and specific reason

### Changed
- `containsReservedClasses()` method now checks for both exact class matches and slash variants
- Skipped patterns are automatically deduplicated to prevent duplicate warnings
- Extract command now displays clear warnings showing why patterns were skipped

### Fixed
- Reserved class detection now properly handles Tailwind named group/peer variants

## 2.2.1 - 2026-05-31

### Added
- Automatic CSS cleanup: `dg:blade-tailwind:restore` now removes restored CSS rules from the output file
- New `removeRestoredClassesFromCss()` method in `TailwindExtractorService` for cleaning up CSS rules
- `inject()` method now returns `restored_classes` array with list of all restored class names
- Test coverage for CSS cleanup functionality

### Changed
- Restore command now automatically cleans up CSS file after restoring classes to Blade files
- Updated documentation to reflect automatic CSS cleanup feature

## 2.2.0 - 2026-05-30

### Added
- Flexible pattern matching support in wrap command
- Support for comma-separated multiple targets in all commands

## 2.1.1 - 2026-05-30

### Fixed
- Enhanced validation to support Tailwind arbitrary values with CSS variables, hex colors, RGB/HSL colors, and complex functions
- Now properly extracts classes like `hover:bg-[var(--secondary-color)]`, `bg-[#1da1f2]`, `text-[rgb(255,0,0)]`, etc.
- Added characters `#`, `,`, and `%` to allowed validation regex for arbitrary value support

### Added
- Comprehensive test suite for arbitrary value extraction (10 new tests)
- Tests cover CSS variables, hex colors, RGB/HSL colors, calc expressions, modifiers, and complex gradients

## 2.1.0 - 2026-05-30

### Added
- New `dg:blade-tailwind:wrap` command for automated class list identification and marking
- Intelligent deduplication: identical class lists automatically receive the same wrapper name
- Semantic wrapper name generation: `__adjective-noun-number__` format (e.g., `__happy-cat-1__`)
- Protected pattern detection: automatically skips `__`, `material-symbols-outlined`, and `TW-` prefixes
- Comprehensive test coverage for wrap command functionality
- Options for wrap command:
  - `--min`: Configurable minimum class count (default: 3)
  - `--skip-prefix`: Skip class lists with specified prefix (default: TW)
  - `--dry-run`: Preview changes without modifying files
- Change summary display showing grouped wrappers with occurrence counts
- Support for `class=""`, `@class([...])`, and wire/x-bind class attributes

### Changed
- Documentation updated with wrap command examples and workflows
- Added "Quick Start: 3-Step Workflow" section to README
- Updated AGENTS.md with comprehensive wrap command architecture details

## 2.0.0 - 2026-05-21

### BREAKING CHANGES
- Split single command into two separate commands:
  - `dg:blade-tailwind:extract` - for extraction only (replaces old `dg:blade-tailwind-extract extract` and `e` alias)
  - `dg:blade-tailwind:restore` - for restoration only (replaces old `dg:blade-tailwind-extract inject` and `r` alias)
- Removed mode argument from commands (no longer needed with separate commands)
- Updated service provider to register both commands

### Added
- New `BladeTailwindRestoreCommand` class for restoration operations
- New `HandlesBulkOperations` trait for shared command functionality
- Improved command structure with better separation of concerns

### Changed
- Refactored `BladeTailwindExtractCommand` to handle extraction only
- Moved shared command methods to `HandlesBulkOperations` trait
- Updated all documentation to reflect new command structure
- Updated tip messages to reference new command names

## 1.1.1 - 2026-05-21

### Changed
- Updated dependencies

## 1.1.0 - 2026-05-21

### Added
- PHP syntax validation (`php -l`) runs before extraction to catch syntax errors early
- Smart file filtering: when extracting without target, only files with extractable patterns (`__name__ classes __`) are shown in confirmation list
- New `hasExtractablePatterns()` method in `TailwindExtractorService` to check for extractable content
- New `lintPhpFiles()` method in command to validate PHP syntax

### Changed
- Made `getBladeFiles()` method public in `TailwindExtractorService` for better testability
- Improved error messages when PHP syntax errors are found

## 1.0.0 - 2026-05-18

- Initial release
- Extract Tailwind classes from Blade templates
- Inject Tailwind classes back into templates
- Support for `class=""`, `->class([...])`, and `@class([...])` syntaxes
- Configurable class prefix and hash length
- Reserved class protection (group, peer)
- Pattern matching for file selection
- Artisan command: `dgtool:blade-tailwind-extract`
