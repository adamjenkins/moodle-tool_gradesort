# Grade category sort (tool_gradesort)

A Moodle admin tool that sorts the contents of a course's grade category.

Moodle's gradebook appends every new grade item to the end, so gradebook order
drifts away from course order as soon as a course is edited. Core has an
`autosort` stub in `grade/edit/tree/index.php` that was never implemented. This
plugin implements it.

## What it does

Pick a grade category and a sort mode:

- **Order in course** — the order the activities appear on the course page.
- **Alphabetically** — by grade item name, natural order ("Quiz 2" before "Quiz 10").
- **Activity type** — grouped by activity type (A-Z), course order within each group.

Optionally apply the same sort to every nested subcategory. You see a preview of
the resulting order before anything is written.

Within each category the contents are arranged in three bands:

1. Activities, ordered by the mode you chose.
2. Other grade items (manual, calculated, imported), always A-Z.
3. Subcategories, always last.

**Sorting never moves a grade item across a category boundary.** Each category is
sorted strictly within its own bounds.

## Requirements

Moodle 5.0–5.3. Requires the `moodle/grade:manage` capability in the course.

## Installation

Copy into `admin/tool/gradesort` (Moodle 5.1+: `public/admin/tool/gradesort`) and
visit Site administration → Notifications.

## Usage

In a course, go to any gradebook page and choose **Grade category sort** from the
gradebook dropdown (under "Setup"). Pick a category and a sort mode, press
**Preview sort** to see the resulting order, then **Apply sort** to write it.

The preview lists the selected category's own contents. If you tick **Also sort
subcategories**, the nested categories are sorted by the same rule but are not
listed individually — the preview says so when the option is on.

## License

GPL v3 or later.
