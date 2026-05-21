# Changelog

All notable changes to `blade-tailwind-extract` will be documented in this file.

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
