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
 * One direct child of a grade category — either a grade item or a subcategory.
 *
 * Both kinds are positioned by a grade_items row: for a subcategory that is its
 * associated itemtype='category' item. Ordering therefore always means writing
 * $gradeitemid's sortorder, whatever the kind.
 *
 * @package    tool_gradesort
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sibling {
    /** @var int Band for real activities (itemtype='mod'). */
    public const BAND_ACTIVITY = 0;

    /** @var int Band for manual, calculated and imported items. */
    public const BAND_ITEM = 1;

    /** @var int Band for subcategories — always last. */
    public const BAND_CATEGORY = 2;

    /**
     * Constructor.
     *
     * @param string $kind 'item' or 'category'
     * @param int $gradeitemid grade_items.id that carries this sibling's sortorder
     * @param string $name display name
     * @param int $sortorder current grade_items.sortorder
     * @param int $band one of the BAND_* constants
     * @param string|null $typename localised activity type name, null when not an activity
     * @param array|null $coursepos [sectionnum, indexinsection, itemnumber], or null when it has no course position
     */
    public function __construct(
        /** @var string 'item' or 'category' */
        public readonly string $kind,
        /** @var int grade_items.id that carries this sibling's sortorder */
        public readonly int $gradeitemid,
        /** @var string display name */
        public readonly string $name,
        /** @var int current grade_items.sortorder */
        public readonly int $sortorder,
        /** @var int one of the BAND_* constants */
        public readonly int $band,
        /** @var string|null localised activity type name */
        public readonly ?string $typename = null,
        /** @var array|null [sectionnum, indexinsection, itemnumber] */
        public readonly ?array $coursepos = null,
    ) {
    }
}
