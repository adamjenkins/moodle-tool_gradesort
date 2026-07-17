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

namespace tool_gradesort\output;

use renderer_base;
use tool_gradesort\local\collector;
use tool_gradesort\local\sibling;
use tool_gradesort\local\sorter;
use tool_gradesort\local\sort_mode;

/**
 * The before/after view of a pending sort.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param int $categoryid the category being previewed
     * @param sort_mode $mode
     */
    public function __construct(
        /** @var int the category being previewed */
        private readonly int $categoryid,
        /** @var sort_mode the mode to preview */
        private readonly sort_mode $mode,
    ) {
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $siblings = collector::collect($this->categoryid);

        // Current order is simply ascending sortorder — that is what the
        // gradebook renders within a category.
        $current = $siblings;
        usort($current, fn(sibling $a, sibling $b): int => $a->sortorder <=> $b->sortorder);
        $sorted = sorter::sort($siblings, $this->mode);

        $rows = [];
        $count = max(count($current), count($sorted));
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'current' => isset($current[$i]) ? $this->row($current[$i]) : null,
                'after' => isset($sorted[$i]) ? $this->row($sorted[$i]) : null,
            ];
        }

        return [
            'rows' => $rows,
            'unchanged' => $this->names($current) === $this->names($sorted),
        ];
    }

    /**
     * One side of a preview row.
     *
     * @param sibling $sibling
     * @return array
     */
    private function row(sibling $sibling): array {
        return [
            'name' => $sibling->name,
            'typename' => $sibling->typename,
            'iscategory' => $sibling->band === sibling::BAND_CATEGORY,
        ];
    }

    /**
     * The names of a list of siblings, in order.
     *
     * @param array $siblings
     * @return array
     */
    private function names(array $siblings): array {
        return array_map(fn(sibling $s): string => $s->name, $siblings);
    }
}
