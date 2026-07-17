<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_gradesort\local;

use grade_category;
use grade_item;

/**
 * Applies a sort to a grade category: collect, sort, write.
 *
 * The permutation step writes only the sortorder values the sibling set already
 * occupies, so on its own it leaves the course-wide multiset of sortorder values
 * unchanged. It never writes categoryid or parent, so nothing crosses a category
 * boundary, and no course-wide UPDATE runs — unlike core's move_after_sortorder(),
 * which shifts every higher sortorder in the course on every single call.
 *
 * If the siblings collide (duplicate sortorders left by activity duplication,
 * course merges or restore), a repair step runs first: core's
 * grade_item::fix_duplicate_sortorder($courseid). That call is course-wide, not
 * scoped to this category, so it can renumber sortorder values belonging to
 * grade items in other, unrelated categories. It is order-preserving — nothing
 * is reordered anywhere by it — but when it runs, the course-wide multiset of
 * sortorder values can change.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applier {
    /**
     * Sort a category's contents, optionally recursing into its subcategories.
     *
     * @param int $categoryid
     * @param sort_mode $mode
     * @param bool $recursive also sort every nested subcategory
     * @return int how many grade items had their sortorder changed
     */
    public static function apply(int $categoryid, sort_mode $mode, bool $recursive): int {
        $categoryids = [$categoryid];
        if ($recursive) {
            $categoryids = array_merge($categoryids, collector::descendant_categories($categoryid));
        }

        $changed = 0;
        foreach ($categoryids as $id) {
            $changed += self::apply_one($id, $mode);
        }
        return $changed;
    }

    /**
     * Sort one category's direct children, within their own sortorder values.
     *
     * @param int $categoryid
     * @param sort_mode $mode
     * @return int how many grade items had their sortorder changed
     */
    private static function apply_one(int $categoryid, sort_mode $mode): int {
        $category = grade_category::fetch(['id' => $categoryid]);
        if (!$category) {
            return 0;
        }

        $siblings = collector::collect($categoryid);
        if (self::has_duplicates($siblings)) {
            // Duplicate sortorders are introduced by activity duplication, course
            // merges and restore. They make the requested order unachievable, so
            // repair with core's own function and re-read.
            grade_item::fix_duplicate_sortorder($category->courseid);
            $siblings = collector::collect($categoryid);
        }

        $pool = array_map(fn(sibling $s): int => $s->sortorder, $siblings);
        sort($pool, SORT_NUMERIC);

        $changed = 0;
        foreach (sorter::sort($siblings, $mode) as $index => $sibling) {
            if ($sibling->sortorder === $pool[$index]) {
                continue;
            }
            // Always write the grade item: grade_category::set_sortorder() just
            // delegates to its own item anyway.
            $item = new grade_item(['id' => $sibling->gradeitemid], true);
            $item->set_sortorder($pool[$index]);
            $changed++;
        }
        return $changed;
    }

    /**
     * Whether any two siblings share a sortorder.
     *
     * @param array $siblings
     * @return bool
     */
    private static function has_duplicates(array $siblings): bool {
        $sortorders = array_map(fn(sibling $s): int => $s->sortorder, $siblings);
        return count(array_unique($sortorders)) !== count($sortorders);
    }
}
