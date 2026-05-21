# Changelog

All notable changes to `blade-tailwind-extract` will be documented in this file.

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
