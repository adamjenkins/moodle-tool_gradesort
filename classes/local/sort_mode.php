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

/**
 * The ways a grade category's contents can be sorted.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum sort_mode: string {
    // Order the activities appear on the course page.
    case COURSE = 'course';

    // Natural alphabetical order of the grade item name.
    case ALPHA = 'alpha';

    // Grouped by activity type, course order within each group.
    case TYPE = 'type';

    /**
     * The localised name of this mode.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('mode_' . $this->value, 'tool_gradesort');
    }

    /**
     * All modes as a select-menu options array.
     *
     * @return array value => localised name
     */
    public static function options(): array {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->get_name();
        }
        return $options;
    }
}
