# Changelog

All notable changes to this plugin are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.2] - 2026-10-04

### Changed

- CI tests `MOODLE_503_STABLE` (blocking rows: PHP 8.3-8.4, PostgreSQL 17,
  MariaDB 11.4) instead of the experimental moodle.git `main` rows, now that
  Moodle 5.3 is released.
- `composer.json`: `moodle/moodle` constraint is now `^5.0` (was `>=5.0 <5.4`),
  so later 5.x releases are not excluded.

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
