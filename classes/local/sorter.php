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

use core_collator;

/**
 * The sort rules. Pure: takes sibling objects, returns sibling objects, touches
 * no database. All the interesting behaviour of the plugin lives here.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sorter {
    /**
     * Order a category's direct children for display.
     *
     * Siblings are split into three bands — activities, other grade items, then
     * subcategories — which are concatenated in that order. Only the activity
     * band is affected by the mode; the other-items band is always name order,
     * and the category band is name order except under COURSE, where a category
     * ranks by the earliest course position of any activity nested inside it.
     *
     * @param array $siblings sibling objects, in any order
     * @param sort_mode $mode
     * @return array re-indexed list of the same objects, in display order
     */
    public static function sort(array $siblings, sort_mode $mode): array {
        $bands = [
            sibling::BAND_ACTIVITY => [],
            sibling::BAND_ITEM => [],
            sibling::BAND_CATEGORY => [],
        ];
        foreach ($siblings as $sibling) {
            $bands[$sibling->band][] = $sibling;
        }

        $activities = match ($mode) {
            sort_mode::ALPHA => self::by_name($bands[sibling::BAND_ACTIVITY]),
            sort_mode::COURSE => self::by_coursepos($bands[sibling::BAND_ACTIVITY]),
            sort_mode::TYPE => self::by_type($bands[sibling::BAND_ACTIVITY]),
        };

        // Categories rank by course position only under COURSE; otherwise by name.
        $categories = $mode === sort_mode::COURSE
            ? self::by_coursepos($bands[sibling::BAND_CATEGORY])
            : self::by_name($bands[sibling::BAND_CATEGORY]);

        return array_merge($activities, self::by_name($bands[sibling::BAND_ITEM]), $categories);
    }

    /**
     * Sort by display name, locale-aware and natural ("Quiz 2" before "Quiz 10").
     *
     * @param array $siblings
     * @return array re-indexed
     */
    private static function by_name(array $siblings): array {
        core_collator::asort_objects_by_property($siblings, 'name', core_collator::SORT_NATURAL);
        return array_values($siblings);
    }

    /**
     * Sort by course position, with name as the tie-break and positionless
     * siblings pushed to the end.
     *
     * Relies on PHP 8's sort being stable: sorting by name first leaves name as
     * the residual order wherever course positions compare equal.
     *
     * @param array $siblings
     * @return array re-indexed
     */
    private static function by_coursepos(array $siblings): array {
        $siblings = self::by_name($siblings);
        usort($siblings, function (sibling $a, sibling $b): int {
            return self::coursepos_key($a) <=> self::coursepos_key($b);
        });
        return $siblings;
    }

    /**
     * Group into activity-type buckets ordered A-Z, course order within each.
     *
     * @param array $siblings
     * @return array re-indexed
     */
    private static function by_type(array $siblings): array {
        $buckets = [];
        foreach ($siblings as $sibling) {
            // An activity always has a type name; fall back defensively so a
            // null can never collapse two buckets into one.
            $buckets[$sibling->typename ?? ''][] = $sibling;
        }
        core_collator::ksort($buckets, core_collator::SORT_NATURAL);

        $ordered = [];
        foreach ($buckets as $bucket) {
            foreach (self::by_coursepos($bucket) as $sibling) {
                $ordered[] = $sibling;
            }
        }
        return $ordered;
    }

    /**
     * The comparable course-position key. Positionless siblings sort last.
     *
     * @param sibling $sibling
     * @return array
     */
    private static function coursepos_key(sibling $sibling): array {
        return $sibling->coursepos ?? [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX];
    }
}
