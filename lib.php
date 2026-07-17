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

/**
 * Callbacks for tool_gradesort.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add this tool to the gradebook navigation dropdown.
 *
 * Note the entry MUST go under the 'settings' key: general_action_bar's
 * get_action_selector() switches on plugin type with no default case, so a new
 * top-level key would be silently dropped from the menu.
 *
 * @param array $plugininfo the gradebook plugin info tree
 * @param int $courseid
 * @return array the modified tree
 */
function tool_gradesort_extend_gradebook_plugininfo(array $plugininfo, int $courseid): array {
    $context = context_course::instance($courseid);
    if (!has_capability('moodle/grade:manage', $context)) {
        return $plugininfo;
    }

    $plugininfo['settings']['gradesort'] = new grade_plugin_info(
        'gradesort',
        new moodle_url('/admin/tool/gradesort/index.php', ['id' => $courseid]),
        get_string('pluginname', 'tool_gradesort')
    );

    return $plugininfo;
}
