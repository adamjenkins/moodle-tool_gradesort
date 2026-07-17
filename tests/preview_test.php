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

use tool_gradesort\local\sort_mode;
use tool_gradesort\output\preview;

/**
 * Tests for the before/after preview.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_gradesort\output\preview
 */
final class preview_test extends \advanced_testcase {
    /**
     * A recursive preview must never claim "unchanged", even when the
     * previewed category's own direct children are already in the requested
     * order: it only ever inspects that one category, so it cannot know
     * whether a descendant (sorted only when Apply actually runs recursively)
     * would change. Claiming "unchanged" here previously meant the preview
     * said nothing would happen while Apply went on to reorder every
     * subcategory.
     */
    public function test_recursive_preview_is_never_reported_unchanged(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $root = \grade_category::fetch_course_category($course->id);

        // The root's own direct child (one activity) is trivially already in
        // alphabetical order — nothing to do at the root level on its own.
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Alpha', 'section' => 1]);

        // A subcategory nested under the root, whose own children are NOT in
        // alphabetical order — a recursive sort would change this.
        $sub = new \grade_category(['courseid' => $course->id, 'fullname' => 'Sub'], false);
        $sub->insert();
        foreach (['Zulu', 'Yankee'] as $name) {
            $mod = $generator->create_module('assign', ['course' => $course->id, 'name' => $name, 'section' => 2]);
            $item = \grade_item::fetch([
                'courseid' => $course->id, 'itemtype' => 'mod',
                'itemmodule' => 'assign', 'iteminstance' => $mod->id,
            ]);
            $item->set_parent($sub->id);
        }

        $renderer = $PAGE->get_renderer('core');

        // Non-recursive: the root's own children are already sorted, so the
        // preview correctly reports nothing would change.
        $nonrecursive = (new preview((int) $root->id, sort_mode::ALPHA, false))->export_for_template($renderer);
        $this->assertTrue($nonrecursive['unchanged']);
        $this->assertFalse($nonrecursive['recursive']);

        // Recursive: a descendant (Sub) would change, so the preview must not
        // report "unchanged" even though the rows it renders (the root's own
        // children only) look identical before and after.
        $recursive = (new preview((int) $root->id, sort_mode::ALPHA, true))->export_for_template($renderer);
        $this->assertFalse($recursive['unchanged']);
        $this->assertTrue($recursive['recursive']);
    }
}
