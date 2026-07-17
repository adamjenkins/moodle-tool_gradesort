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
 * Sort a grade category's contents.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/lib.php');

use tool_gradesort\event\category_sorted;
use tool_gradesort\form\sort_form;
use tool_gradesort\local\applier;
use tool_gradesort\local\sort_mode;
use tool_gradesort\output\preview;

$courseid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/grade:manage', $context);

$pageurl = new moodle_url('/admin/tool/gradesort/index.php', ['id' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'tool_gradesort'));
$PAGE->set_heading($course->fullname);

$returnurl = new moodle_url('/grade/edit/tree/index.php', ['id' => $courseid]);

// Build the category options: the whole tree, indented by depth.
$categories = [];
$tree = grade_category::fetch_course_tree($courseid, true);
$stack = [[$tree, 0]];
while ($stack) {
    [$node, $depth] = array_shift($stack);
    if (($node['type'] ?? '') === 'category') {
        $categories[$node['object']->id] = str_repeat('- ', $depth) . $node['object']->get_name();
        $children = array_values($node['children'] ?? []);
        foreach (array_reverse($children) as $child) {
            array_unshift($stack, [$child, $depth + 1]);
        }
    }
}

$form = new sort_form($pageurl, ['courseid' => $courseid, 'categories' => $categories]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$data = $form->get_data();

// Stage 3: apply. Guarded by sesskey and an explicit confirm.
if ($confirm && confirm_sesskey()) {
    $categoryid = required_param('categoryid', PARAM_INT);
    $rawmode = required_param('mode', PARAM_ALPHA);
    $mode = sort_mode::tryFrom($rawmode);
    if ($mode === null) {
        throw new moodle_exception('invalidmode', 'error', '', $rawmode);
    }
    $recursive = optional_param('recursive', 0, PARAM_BOOL);

    // The category must belong to this course — never trust the posted id.
    $category = grade_category::fetch(['id' => $categoryid, 'courseid' => $courseid]);
    if (!$category) {
        throw new moodle_exception('invalidcategory', 'error');
    }

    $changed = applier::apply($categoryid, $mode, (bool) $recursive);

    category_sorted::create([
        'context' => $context,
        'objectid' => $categoryid,
        'other' => [
            'categoryid' => $categoryid,
            'mode' => $mode->value,
            'recursive' => (bool) $recursive,
            'changed' => $changed,
        ],
    ])->trigger();

    if ($changed === 0) {
        redirect(
            $returnurl,
            get_string('nothingtodo', 'tool_gradesort'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    redirect(
        $returnurl,
        get_string('sortapplied', 'tool_gradesort', $changed),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

print_grade_page_head(
    $courseid,
    'settings',
    'gradesort',
    get_string('pluginname', 'tool_gradesort')
);

// Stage 2: preview.
if ($data) {
    $category = grade_category::fetch(['id' => $data->categoryid, 'courseid' => $courseid]);
    if (!$category) {
        throw new moodle_exception('invalidcategory', 'error');
    }
    $mode = sort_mode::tryFrom($data->mode ?? '');
    if ($mode === null) {
        throw new moodle_exception('invalidmode', 'error', '', $data->mode ?? '');
    }

    echo $OUTPUT->heading(get_string('previewheading', 'tool_gradesort', $category->get_name()), 3);
    echo $OUTPUT->render(new preview((int) $data->categoryid, $mode, (bool) $data->recursive));

    $applyurl = new moodle_url($pageurl, [
        'confirm' => 1,
        'categoryid' => $data->categoryid,
        'mode' => $data->mode,
        'recursive' => $data->recursive,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($applyurl, get_string('applysort', 'tool_gradesort'), 'post');
    echo $OUTPUT->single_button($pageurl, get_string('cancel'), 'get');
} else {
    // Stage 1: the form.
    $form->display();
}

echo $OUTPUT->footer();
