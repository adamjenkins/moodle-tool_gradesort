# Changelog

All notable changes to this plugin are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.1] - 2026-10-04

### Added

- `LICENSE` file (GPL-3.0-or-later).
- Tagged releases are published to the camp plugin registry.

### Changed

- Declare Moodle 5.3 support; `composer.json` allows Moodle 5.3.
- `.gitattributes` keeps development files out of the distribution ZIP.

## [1.0.0] - 2026-07-17

### Added

- Sort the direct children of a grade category by order in course, alphabetically, or by activity type.
- Optional recursion into nested subcategories.
- Preview of the resulting order before it is applied.
- `\tool_gradesort\event\category_sorted` event for auditing.
