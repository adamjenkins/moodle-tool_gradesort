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

namespace tool_gradesort;

use tool_gradesort\local\applier;
use tool_gradesort\local\sort_mode;

/**
 * Tests for writing a new order back to the gradebook.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_gradesort\local\applier
 */
final class applier_test extends \advanced_testcase {
    /**
     * Every sortorder value in use in a course, ascending.
     *
     * @param int $courseid
     * @return array
     */
    private function course_sortorders(int $courseid): array {
        global $DB;
        $values = $DB->get_fieldset_select('grade_items', 'sortorder', 'courseid = ?', [$courseid]);
        $values = array_map('intval', $values);
        sort($values);
        return $values;
    }

    /**
     * The display order of a category's children, by name, as the gradebook
     * itself would render it.
     *
     * @param int $courseid
     * @param int $categoryid
     * @return array
     */
    private function displayed_order(int $courseid, int $categoryid): array {
        $tree = \grade_category::fetch_course_tree($courseid, true);
        $found = $this->find_category($tree, $categoryid);
        $names = [];
        foreach ($found['children'] ?? [] as $child) {
            if ($child['type'] === 'courseitem' || $child['type'] === 'categoryitem') {
                continue; // The category's own total — core places this, not us.
            }
            $names[] = $child['object']->get_name();
        }
        return $names;
    }

    /**
     * Locate a category node in a fetched course tree.
     *
     * @param array $node
     * @param int $categoryid
     * @return array|null
     */
    private function find_category(array $node, int $categoryid): ?array {
        // A category node carries a grade_category in 'object' and has children;
        // item nodes carry a grade_item, whose ids live in a different sequence.
        if (
            isset($node['children']) && isset($node['object']->id)
                && (int) $node['object']->id === $categoryid
        ) {
            return $node;
        }
        foreach ($node['children'] ?? [] as $child) {
            $found = $this->find_category($child, $categoryid);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * Build a course whose gradebook order is deliberately wrong.
     *
     * @return array [course, rootcategoryid]
     */
    private function course_with_misordered_gradebook(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5]);
        // Create in reverse-alphabetical, reverse-course order: new grade items
        // are appended at MAX(sortorder)+1, so creation order is gradebook order.
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Charlie', 'section' => 3]);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Bravo', 'section' => 2]);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Alpha', 'section' => 1]);
        $root = \grade_category::fetch_course_category($course->id);
        return [$course, (int) $root->id];
    }

    /**
     * THE load-bearing test. Sorting permutes only the sortorder values the
     * sibling set already holds, so the course-wide multiset cannot change.
     * If this fails, the design is wrong — do not "fix" the test.
     */
    public function test_sortorder_multiset_is_unchanged(): void {
        $this->resetAfterTest();
        [$course, $rootid] = $this->course_with_misordered_gradebook();

        $before = $this->course_sortorders($course->id);
        applier::apply($rootid, sort_mode::ALPHA, false);
        $after = $this->course_sortorders($course->id);

        $this->assertSame($before, $after);
    }

    /**
     * Alphabetical sort actually changes what the gradebook renders.
     */
    public function test_alpha_sort_changes_displayed_order(): void {
        $this->resetAfterTest();
        [$course, $rootid] = $this->course_with_misordered_gradebook();

        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->displayed_order($course->id, $rootid));
        $changed = applier::apply($rootid, sort_mode::ALPHA, false);
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->displayed_order($course->id, $rootid));
        $this->assertGreaterThan(0, $changed);
    }

    /**
     * Course-order sort restores course-page order.
     */
    public function test_course_sort_restores_course_order(): void {
        $this->resetAfterTest();
        [$course, $rootid] = $this->course_with_misordered_gradebook();

        applier::apply($rootid, sort_mode::COURSE, false);
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->displayed_order($course->id, $rootid));
    }

    /**
     * An already-sorted category writes nothing at all.
     */
    public function test_already_sorted_category_writes_nothing(): void {
        $this->resetAfterTest();
        [, $rootid] = $this->course_with_misordered_gradebook();

        applier::apply($rootid, sort_mode::ALPHA, false);
        $this->assertSame(0, applier::apply($rootid, sort_mode::ALPHA, false));
    }

    /**
     * Nothing may cross a category boundary: an item in a subcategory stays in
     * that subcategory (is never reparented), and its sortorder is untouched by
     * a non-recursive sort of the parent category — it must not be pulled into
     * the parent's sibling pool and renumbered.
     */
    public function test_sorting_never_crosses_category_bounds(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5]);
        $root = \grade_category::fetch_course_category($course->id);

        $sub = new \grade_category(['courseid' => $course->id, 'fullname' => 'Sub'], false);
        $sub->insert();

        // Named to sort alphabetically ahead of the outside item, so a leak into
        // the parent's activity band would not by coincidence reassign it its
        // own existing sortorder value.
        $inside = $generator->create_module('assign', ['course' => $course->id, 'name' => 'Aaron inside', 'section' => 1]);
        $insideitem = \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'assign', 'iteminstance' => $inside->id,
        ]);
        $insideitem->set_parent($sub->id);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Alpha outside', 'section' => 2]);

        $sortorderbefore = (int) \grade_item::fetch(['id' => $insideitem->id])->sortorder;

        applier::apply((int) $root->id, sort_mode::ALPHA, false);

        $reloaded = \grade_item::fetch(['id' => $insideitem->id]);
        $this->assertSame(
            (int) $sub->id,
            (int) $reloaded->categoryid,
            'Sorting the root category must not reparent anything'
        );
        $this->assertSame(
            $sortorderbefore,
            (int) $reloaded->sortorder,
            'Sorting the root category must not renumber an item inside a subcategory'
        );
    }

    /**
     * Recursion sorts descendants too; without it they are left alone.
     */
    public function test_recursion_sorts_subcategories(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5]);
        $root = \grade_category::fetch_course_category($course->id);

        $sub = new \grade_category(['courseid' => $course->id, 'fullname' => 'Sub'], false);
        $sub->insert();

        foreach (['Yankee', 'Xray'] as $name) {
            $mod = $generator->create_module('assign', ['course' => $course->id, 'name' => $name, 'section' => 1]);
            $item = \grade_item::fetch([
                'courseid' => $course->id, 'itemtype' => 'mod',
                'itemmodule' => 'assign', 'iteminstance' => $mod->id,
            ]);
            $item->set_parent($sub->id);
        }

        // Non-recursive: the subcategory keeps its creation order.
        applier::apply((int) $root->id, sort_mode::ALPHA, false);
        $this->assertSame(['Yankee', 'Xray'], $this->displayed_order($course->id, (int) $sub->id));

        // Recursive: the subcategory is sorted too.
        applier::apply((int) $root->id, sort_mode::ALPHA, true);
        $this->assertSame(['Xray', 'Yankee'], $this->displayed_order($course->id, (int) $sub->id));
    }

    /**
     * Duplicate sortorders (which restore and course-merge really do create) are
     * repaired via core's own fix_duplicate_sortorder before sorting, so the
     * requested order is still achieved.
     */
    public function test_duplicate_sortorders_are_repaired_first(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $rootid] = $this->course_with_misordered_gradebook();

        // Force a duplicate, exactly as a course merge would.
        $items = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'mod'",
            [$course->id],
            'sortorder ASC'
        );
        $first = reset($items);
        $second = next($items);
        $DB->set_field('grade_items', 'sortorder', $first->sortorder, ['id' => $second->id]);

        applier::apply($rootid, sort_mode::ALPHA, false);

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->displayed_order($course->id, $rootid));

        $sortorders = $this->course_sortorders($course->id);
        $this->assertSame(
            array_values(array_unique($sortorders)),
            $sortorders,
            'No duplicate sortorders should remain'
        );
    }
}
