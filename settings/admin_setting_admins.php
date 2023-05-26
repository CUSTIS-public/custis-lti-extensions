<?php
//    Moodle LTI Extensions
//    Copyright (C) 2023 CUSTIS
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Выпадающий список с полями с информацией о пользователе
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Выпадающий список с администраторами
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_admins extends admin_setting_configselect
{
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct($name, $visiblename, $description)
    {
        global $CFG;

        $defaultAdmin = null;
        if (!empty($CFG->siteadmins)) {
            $adminids = explode(',', $CFG->siteadmins);
            $defaultAdmin = $adminids[0];
        }

        parent::__construct(
            $name,
            $visiblename,
            $description,
            $defaultAdmin,
            null
        );
    }

    /**
     * Loads an array of choices for the configselect control
     *
     * @return bool always return true
     */
    public function load_choices()
    {
        if (is_array($this->choices)) {
            return true;
        }
        $this->choices = array();

        $admins = get_admins();

        foreach ($admins as $id => $admin) {
            $this->choices[$id] = $admin->firstname . ' ' . $admin->lastname;
        }
        return true;
    }
}
