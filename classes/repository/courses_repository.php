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
 * Courses repository
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ltiextensions\repository;

defined('MOODLE_INTERNAL') || die();

class courses_repository
{
    /**
     * Returns courses, that are not published as LTI tools yet.
     *
     * @return array
     */
    public static function get_not_published_courses()
    {
        global $DB;

        $sql = "SELECT mc.*, c.id as contextid FROM {course} mc 
        join {context} c
        on c.instanceid = mc.id
        WHERE c.contextlevel in (" . CONTEXT_COURSE . ")
        and c.id not in (SELECT t.contextid FROM {enrol_lti_tools} t WHERE t.ltiversion = 'LTI-1p3')";

        return $DB->get_records_sql($sql);
    }

    /**
     * Returns modules, that are not published as LTI tools yet.
     *
     * @return array
     */
    public static function get_not_published_modules()
    {
        global $DB;

        $sql = "SELECT mmc.*, c.id as contextid FROM {course_modules} mmc 
        join {context} c
        on c.instanceid = mmc.id
        WHERE c.contextlevel in (" . CONTEXT_MODULE . ")
        and c.id not in (SELECT t.contextid FROM {enrol_lti_tools} t WHERE t.ltiversion = 'LTI-1p3')";

        return $DB->get_records_sql($sql);
    }

    /**
     * Получает курсы по их idnumber
     *
     * @return array курсы
     */
    public static function get_courses_by_idnumbers(array $idnumbers)
    {
        if (count($idnumbers) == 0) {
            return array();
        }

        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($idnumbers);
        $sql = "SELECT id, idnumber FROM {course} mc WHERE idnumber $insql";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Получает курсы по их shortname
     *
     * @return array курсы
     */
    public static function get_courses_by_shortnames(array $shortnames)
    {
        if (count($shortnames) == 0) {
            return array();
        }

        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($shortnames);
        $sql = "SELECT id, shortname FROM {course} mc WHERE shortname $insql";

        return $DB->get_records_sql($sql, $params);
    }
}
