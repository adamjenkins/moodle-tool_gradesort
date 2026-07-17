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
 * Reads a grade category's direct children into sibling objects.
 *
 * This is the only unit that knows how the gradebook stores its tree. Note that
 * a category is positioned by its own itemtype='category' grade item, whose
 * iteminstance is the category id — a different meaning of iteminstance than
 * for itemtype='mod' rows.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector {
    /**
     * The direct children of a grade category, unordered.
     *
     * Excludes the category's own total item: core forces its display position
     * regardless of sortorder, so it is not ours to move.
     *
     * @param int $categoryid
     * @return array sibling objects
     */
    public static function collect(int $categoryid): array {
        global $DB;

        $category = grade_category::fetch(['id' => $categoryid]);
        if (!$category) {
            return [];
        }
        $courseid = (int) $category->courseid;
        $positions = self::course_positions($courseid);

        $siblings = [];

        // Plain items. itemtype NOT IN ('course','category') excludes both this
        // category's own total and every subcategory's carrier item.
        $items = $DB->get_records_select(
            'grade_items',
            'categoryid = :categoryid AND itemtype NOT IN (:course, :category)',
            ['categoryid' => $categoryid, 'course' => 'course', 'category' => 'category']
        );
        foreach ($items as $record) {
            $item = new grade_item($record, false);
            $isactivity = $item->itemtype === 'mod';
            $siblings[] = new sibling(
                'item',
                (int) $item->id,
                $item->get_name(),
                (int) $item->sortorder,
                $isactivity ? sibling::BAND_ACTIVITY : sibling::BAND_ITEM,
                $isactivity ? self::type_name($item->itemmodule) : null,
                $isactivity ? self::item_position($item, $positions) : null,
            );
        }

        // Subcategories, each carried by its own category grade item.
        $subcategories = $DB->get_records('grade_categories', ['parent' => $categoryid]);
        foreach ($subcategories as $record) {
            $subcategory = new grade_category($record, false);
            $carrier = $subcategory->load_grade_item();
            $siblings[] = new sibling(
                'category',
                (int) $carrier->id,
                $subcategory->get_name(),
                (int) $carrier->sortorder,
                sibling::BAND_CATEGORY,
                null,
                self::earliest_descendant_position((int) $subcategory->id, $courseid, $positions),
            );
        }

        return $siblings;
    }

    /**
     * Every category nested below the given one, at any depth.
     *
     * @param int $categoryid
     * @return array category ids, excluding $categoryid itself
     */
    public static function descendant_categories(int $categoryid): array {
        global $DB;

        $category = grade_category::fetch(['id' => $categoryid]);
        if (!$category) {
            return [];
        }
        // The path column looks like '/1/7/12/'; descendants are the rows whose
        // path contains '/<id>/' and which are not the row itself.
        $like = $DB->sql_like('path', ':path');
        $records = $DB->get_records_select(
            'grade_categories',
            "courseid = :courseid AND id <> :selfid AND {$like}",
            [
                'courseid' => $category->courseid,
                'selfid' => $categoryid,
                'path' => '%/' . $DB->sql_like_escape((string) $categoryid) . '/%',
            ],
            '',
            'id'
        );
        return array_map('intval', array_keys($records));
    }

    /**
     * Map every cmid in a course to its [sectionnum, indexinsection] position.
     *
     * Course-page order lives in course_sections.sequence — a comma-separated
     * cmid list — so position is the index within that string, not a column.
     *
     * @param int $courseid
     * @return array cmid => [sectionnum, indexinsection]
     */
    private static function course_positions(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $positions = [];
        foreach ($modinfo->get_sections() as $sectionnum => $cmids) {
            foreach (array_values($cmids) as $index => $cmid) {
                $positions[(int) $cmid] = [(int) $sectionnum, $index];
            }
        }
        return $positions;
    }

    /**
     * The course position of an activity's grade item.
     *
     * @param grade_item $item
     * @param array $positions cmid => [sectionnum, indexinsection]
     * @return array|null [sectionnum, indexinsection, itemnumber], or null if the
     *                    item has no live course module (an orphaned grade item)
     */
    private static function item_position(grade_item $item, array $positions): ?array {
        $modinfo = get_fast_modinfo($item->courseid);
        $instances = $modinfo->get_instances();
        if (empty($instances[$item->itemmodule][$item->iteminstance])) {
            return null;
        }
        $cm = $instances[$item->itemmodule][$item->iteminstance];
        if (!isset($positions[(int) $cm->id])) {
            return null;
        }
        // One activity can own several grade items; itemnumber orders them.
        return array_merge($positions[(int) $cm->id], [(int) $item->itemnumber]);
    }

    /**
     * The earliest course position of any activity nested below a category.
     *
     * This is what gives a subcategory a meaningful rank under the course-order
     * mode — a category has no course position of its own.
     *
     * @param int $categoryid
     * @param int $courseid
     * @param array $positions cmid => [sectionnum, indexinsection]
     * @return array|null earliest [sectionnum, indexinsection, itemnumber], or null
     */
    private static function earliest_descendant_position(int $categoryid, int $courseid, array $positions): ?array {
        global $DB;

        $categoryids = array_merge([$categoryid], self::descendant_categories($categoryid));
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $params['courseid'] = $courseid;
        $params['itemtype'] = 'mod';
        $records = $DB->get_records_select(
            'grade_items',
            "courseid = :courseid AND itemtype = :itemtype AND categoryid {$insql}",
            $params
        );

        $earliest = null;
        foreach ($records as $record) {
            $position = self::item_position(new grade_item($record, false), $positions);
            if ($position !== null && ($earliest === null || $position < $earliest)) {
                $earliest = $position;
            }
        }
        return $earliest;
    }

    /**
     * The localised display name of an activity type.
     *
     * @param string $modname e.g. 'quiz'
     * @return string e.g. 'Quiz'
     */
    private static function type_name(string $modname): string {
        return get_string('modulename', $modname);
    }
}
