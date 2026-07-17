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

namespace tool_gradesort\event;

/**
 * Fired when a grade category's contents are reordered.
 *
 * Without this a sort is invisible in the logs except as N anonymous
 * grade_item_updated records.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 * @var int $categoryid the grade category that was sorted
 * @var string $mode the sort_mode value used
 * @var bool $recursive whether subcategories were sorted too
 * @var int $changed how many grade items were reordered
 * }
 */
class category_sorted extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grade_categories';
    }

    /**
     * The event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventcategorysorted', 'tool_gradesort');
    }

    /**
     * A plain-text description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' sorted the grade category with id " .
            "'{$this->other['categoryid']}' in the course with id '{$this->courseid}' " .
            "by '{$this->other['mode']}', reordering {$this->other['changed']} grade items.";
    }

    /**
     * Where the event happened.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/grade/edit/tree/index.php', ['id' => $this->courseid]);
    }
}
