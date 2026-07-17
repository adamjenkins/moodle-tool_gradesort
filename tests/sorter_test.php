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

use tool_gradesort\local\sibling;
use tool_gradesort\local\sorter;
use tool_gradesort\local\sort_mode;

/**
 * Tests for the pure sort rules.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_gradesort\local\sorter
 */
final class sorter_test extends \advanced_testcase {
    /**
     * Build an activity sibling.
     *
     * @param string $name
     * @param int $sortorder
     * @param string|null $typename
     * @param array|null $coursepos
     * @return sibling
     */
    private function activity(
        string $name,
        int $sortorder,
        ?string $typename = 'Assignment',
        ?array $coursepos = null
    ): sibling {
        return new sibling(
            'item',
            $sortorder * 100,
            $name,
            $sortorder,
            sibling::BAND_ACTIVITY,
            $typename,
            $coursepos
        );
    }

    /**
     * Build a non-activity item sibling (manual, calculated, imported).
     *
     * @param string $name
     * @param int $sortorder
     * @return sibling
     */
    private function item(string $name, int $sortorder): sibling {
        return new sibling('item', $sortorder * 100, $name, $sortorder, sibling::BAND_ITEM);
    }

    /**
     * Build a subcategory sibling.
     *
     * @param string $name
     * @param int $sortorder
     * @param array|null $coursepos earliest course position of a descendant activity
     * @return sibling
     */
    private function category(string $name, int $sortorder, ?array $coursepos = null): sibling {
        return new sibling(
            'category',
            $sortorder * 100,
            $name,
            $sortorder,
            sibling::BAND_CATEGORY,
            null,
            $coursepos
        );
    }

    /**
     * Reduce a sort result to its names for readable assertions.
     *
     * @param array $siblings
     * @return array
     */
    private function names(array $siblings): array {
        return array_map(fn(sibling $s): string => $s->name, $siblings);
    }

    /**
     * Categories always sink below every item, whatever the mode.
     */
    public function test_categories_always_last(): void {
        $input = [
            $this->category('Aardvark category', 1),
            $this->activity('Zebra assignment', 2, 'Assignment', [0, 0, 0]),
            $this->item('Manual mark', 3),
        ];
        foreach (sort_mode::cases() as $mode) {
            $this->assertSame(
                ['Zebra assignment', 'Manual mark', 'Aardvark category'],
                $this->names(sorter::sort($input, $mode)),
                "Failed for mode {$mode->value}"
            );
        }
    }

    /**
     * Non-activity items form a band between activities and categories.
     */
    public function test_bands_are_activities_then_items_then_categories(): void {
        $input = [
            $this->item('Zeta manual', 1),
            $this->category('Beta category', 2),
            $this->activity('Alpha quiz', 3, 'Quiz', [0, 0, 0]),
        ];
        $this->assertSame(
            ['Alpha quiz', 'Zeta manual', 'Beta category'],
            $this->names(sorter::sort($input, sort_mode::ALPHA))
        );
    }

    /**
     * Alphabetical mode uses natural ordering, so Quiz 2 precedes Quiz 10.
     */
    public function test_alpha_is_natural_not_lexical(): void {
        $input = [
            $this->activity('Quiz 10', 1),
            $this->activity('Quiz 2', 2),
            $this->activity('Quiz 1', 3),
        ];
        $this->assertSame(
            ['Quiz 1', 'Quiz 2', 'Quiz 10'],
            $this->names(sorter::sort($input, sort_mode::ALPHA))
        );
    }

    /**
     * Course order sorts by section, then position in section, then itemnumber.
     */
    public function test_course_order_uses_section_then_position_then_itemnumber(): void {
        $input = [
            $this->activity('Second in section 2', 1, 'Assignment', [2, 1, 0]),
            $this->activity('Grade 2 of first activity', 2, 'Assignment', [1, 0, 1]),
            $this->activity('First in section 2', 3, 'Assignment', [2, 0, 0]),
            $this->activity('Grade 1 of first activity', 4, 'Assignment', [1, 0, 0]),
        ];
        $this->assertSame(
            [
                'Grade 1 of first activity',
                'Grade 2 of first activity',
                'First in section 2',
                'Second in section 2',
            ],
            $this->names(sorter::sort($input, sort_mode::COURSE))
        );
    }

    /**
     * An activity with no course position (orphaned grade item) sorts to the end
     * of its band by name rather than to the front.
     */
    public function test_course_order_puts_positionless_activities_last(): void {
        $input = [
            $this->activity('Orphan B', 1, 'Assignment', null),
            $this->activity('Orphan A', 2, 'Assignment', null),
            $this->activity('Real activity', 3, 'Assignment', [5, 0, 0]),
        ];
        $this->assertSame(
            ['Real activity', 'Orphan A', 'Orphan B'],
            $this->names(sorter::sort($input, sort_mode::COURSE))
        );
    }

    /**
     * Type mode orders buckets A-Z by type name and uses course order inside each.
     */
    public function test_type_mode_buckets_alpha_course_order_within(): void {
        $input = [
            $this->activity('Final', 1, 'Quiz', [9, 0, 0]),
            $this->activity('Essay 2', 2, 'Assignment', [3, 0, 0]),
            $this->activity('Midterm', 3, 'Quiz', [4, 0, 0]),
            $this->activity('Debate', 4, 'Forum', [2, 0, 0]),
            $this->activity('Essay 1', 5, 'Assignment', [1, 0, 0]),
        ];
        $this->assertSame(
            ['Essay 1', 'Essay 2', 'Debate', 'Midterm', 'Final'],
            $this->names(sorter::sort($input, sort_mode::TYPE))
        );
    }

    /**
     * Under course order a subcategory ranks by the earliest course position of
     * any activity nested inside it.
     */
    public function test_course_order_ranks_categories_by_earliest_descendant(): void {
        $input = [
            $this->category('Later category', 1, [8, 0, 0]),
            $this->category('Earlier category', 2, [2, 0, 0]),
        ];
        $this->assertSame(
            ['Earlier category', 'Later category'],
            $this->names(sorter::sort($input, sort_mode::COURSE))
        );
    }

    /**
     * A category holding no activities has no course position and falls back to
     * name order, after the categories that do have one.
     */
    public function test_course_order_category_without_activities_falls_back_to_name(): void {
        $input = [
            $this->category('Beta empty', 1, null),
            $this->category('Alpha empty', 2, null),
            $this->category('Has activities', 3, [7, 0, 0]),
        ];
        $this->assertSame(
            ['Has activities', 'Alpha empty', 'Beta empty'],
            $this->names(sorter::sort($input, sort_mode::COURSE))
        );
    }

    /**
     * Equal course positions are broken by name, deterministically.
     */
    public function test_ties_are_broken_by_name(): void {
        $input = [
            $this->activity('Bravo', 1, 'Assignment', [1, 0, 0]),
            $this->activity('Alpha', 2, 'Assignment', [1, 0, 0]),
        ];
        $this->assertSame(
            ['Alpha', 'Bravo'],
            $this->names(sorter::sort($input, sort_mode::COURSE))
        );
    }

    /**
     * Degenerate inputs must not explode.
     */
    public function test_empty_and_single_element_sets(): void {
        $this->assertSame([], sorter::sort([], sort_mode::ALPHA));
        $single = [$this->activity('Only', 1)];
        $this->assertSame(['Only'], $this->names(sorter::sort($single, sort_mode::ALPHA)));
    }

    /**
     * The result is a re-indexed list, not a sparse array with original keys.
     */
    public function test_result_is_reindexed(): void {
        $input = [
            5 => $this->activity('B', 1),
            9 => $this->activity('A', 2),
        ];
        $result = sorter::sort($input, sort_mode::ALPHA);
        $this->assertSame([0, 1], array_keys($result));
    }
}
