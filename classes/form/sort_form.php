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

namespace tool_gradesort\form;

use tool_gradesort\local\sort_mode;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Choose a grade category, a sort mode, and whether to recurse.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sort_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $categories = $this->_customdata['categories'];

        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'categoryid', get_string('categorytosort', 'tool_gradesort'), $categories);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('select', 'mode', get_string('sortmode', 'tool_gradesort'), sort_mode::options());
        $mform->setType('mode', PARAM_ALPHA);

        $mform->addElement('advcheckbox', 'recursive', '', get_string('recursive', 'tool_gradesort'));
        $mform->setType('recursive', PARAM_BOOL);
        $mform->addHelpButton('recursive', 'recursive', 'tool_gradesort');

        $this->add_action_buttons(false, get_string('preview', 'tool_gradesort'));
    }
}
