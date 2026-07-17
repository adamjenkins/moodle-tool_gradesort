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

use tool_gradesort\local\collector;
use tool_gradesort\local\sibling;

/**
 * Tests for reading a grade category's direct children.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_gradesort\local\collector
 */
final class collector_test extends \advanced_testcase {
    /**
     * Reduce siblings to names for readable assertions.
     *
     * @param array $siblings
     * @return array
     */
    private function names(array $siblings): array {
        $names = array_map(fn(sibling $s): string => $s->name, $siblings);
        sort($names);
        return $names;
    }

    /**
     * The root category's children are the course's items and its subcategories,
     * and never the course total item itself.
     */
    public function test_collect_excludes_the_categorys_own_total_item(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Essay']);
        $root = \grade_category::fetch_course_category($course->id);

        $siblings = collector::collect($root->id);

        // The course total item is itemtype='course', iteminstance=$root->id. It
        // must not appear as a child of its own category.
        $this->assertSame(['Essay'], $this->names($siblings));
    }

    /**
     * A subcategory appears as a sibling, carried by its category grade item,
     * and lands in the category band.
     */
    public function test_collect_returns_subcategories_in_the_category_band(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $root = \grade_category::fetch_course_category($course->id);

        $sub = new \grade_category(['courseid' => $course->id, 'fullname' => 'Group work'], false);
        $sub->insert();

        $siblings = collector::collect($root->id);
        $this->assertSame(['Group work'], $this->names($siblings));

        $category = reset($siblings);
        $this->assertSame('category', $category->kind);
        $this->assertSame(sibling::BAND_CATEGORY, $category->band);

        // The sibling must be carried by the subcategory's own grade item.
        $expecteditem = $sub->load_grade_item();
        $this->assertSame((int) $expecteditem->id, $category->gradeitemid);
    }

    /**
     * Activities land in the activity band with their localised type name and a
     * course position; manual items land in the item band with neither.
     */
    public function test_collect_bands_and_annotates(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 3]);
        $root = \grade_category::fetch_course_category($course->id);

        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Essay', 'section' => 1]);

        $manual = new \grade_item([
            'courseid' => $course->id,
            'itemtype' => 'manual',
            'itemname' => 'Participation',
            'categoryid' => $root->id,
        ], false);
        $manual->insert();

        $siblings = collector::collect($root->id);
        $byname = [];
        foreach ($siblings as $sibling) {
            $byname[$sibling->name] = $sibling;
        }

        $this->assertSame(sibling::BAND_ACTIVITY, $byname['Essay']->band);
        $this->assertSame(get_string('modulename', 'assign'), $byname['Essay']->typename);
        $this->assertIsArray($byname['Essay']->coursepos);
        $this->assertSame(1, $byname['Essay']->coursepos[0]);

        $this->assertSame(sibling::BAND_ITEM, $byname['Participation']->band);
        $this->assertNull($byname['Participation']->typename);
        $this->assertNull($byname['Participation']->coursepos);
    }

    /**
     * Course position orders by section, then by position within the section's
     * sequence — not by creation order.
     */
    public function test_course_position_follows_section_sequence_not_creation_order(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 3]);
        $root = \grade_category::fetch_course_category($course->id);

        // Create in an order deliberately unlike the course order.
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Late', 'section' => 2]);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Early', 'section' => 1]);

        $siblings = collector::collect($root->id);
        $byname = [];
        foreach ($siblings as $sibling) {
            $byname[$sibling->name] = $sibling;
        }

        $this->assertSame(1, $byname['Early']->coursepos[0]);
        $this->assertSame(2, $byname['Late']->coursepos[0]);
        $this->assertLessThan($byname['Late']->coursepos, $byname['Early']->coursepos);
    }

    /**
     * A subcategory's course position is the earliest of its descendants; a
     * subcategory with no activities has none.
     */
    public function test_subcategory_coursepos_is_earliest_descendant(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5]);
        $root = \grade_category::fetch_course_category($course->id);

        $withactivities = new \grade_category(['courseid' => $course->id, 'fullname' => 'Full'], false);
        $withactivities->insert();
        $empty = new \grade_category(['courseid' => $course->id, 'fullname' => 'Empty'], false);
        $empty->insert();

        $late = $generator->create_module('assign', ['course' => $course->id, 'name' => 'Late', 'section' => 4]);
        $early = $generator->create_module('assign', ['course' => $course->id, 'name' => 'Early', 'section' => 2]);
        foreach ([$late, $early] as $mod) {
            $item = \grade_item::fetch([
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $mod->id,
            ]);
            $item->set_parent($withactivities->id);
        }

        $siblings = collector::collect($root->id);
        $byname = [];
        foreach ($siblings as $sibling) {
            $byname[$sibling->name] = $sibling;
        }

        $this->assertSame(2, $byname['Full']->coursepos[0], 'Should take the EARLIEST descendant position');
        $this->assertNull($byname['Empty']->coursepos);
    }

    /**
     * descendant_categories walks the whole subtree, excluding the root itself.
     */
    public function test_descendant_categories_walks_the_subtree(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $root = \grade_category::fetch_course_category($course->id);

        $parent = new \grade_category(['courseid' => $course->id, 'fullname' => 'Parent'], false);
        $parent->insert();
        $child = new \grade_category(['courseid' => $course->id, 'fullname' => 'Child'], false);
        $child->insert();
        $child->set_parent($parent->id);

        $descendants = collector::descendant_categories($root->id);
        sort($descendants);
        $expected = [(int) $parent->id, (int) $child->id];
        sort($expected);
        $this->assertSame($expected, $descendants);

        $this->assertSame([(int) $child->id], collector::descendant_categories($parent->id));
        $this->assertSame([], collector::descendant_categories($child->id));
    }
}
