# Changelog

All notable changes to `blade-tailwind-extract` will be documented in this file.

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
