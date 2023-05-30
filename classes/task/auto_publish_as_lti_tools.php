<?php
//    Custis LTI Extensions
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


namespace tool_ltiextensions\task;

use core\task\scheduled_task;
use moodle_exception;
use Throwable;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\repository\courses_repository;

/**
 * The task publishes all not published courses with their modules as LTI tools
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_publish_as_lti_tools extends scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('auto_publish_as_lti_tools', 'tool_ltiextensions');
    }

    private function get_fields($courseid, $contextid){
        return [
            "courseid" => $courseid,
            "contextid" => "$contextid",
            "secret" => random_string(32),
            "id" => 0,
            "type" => "lti",
            "ltiversion" => "LTI-1p3",
            "name" => "",
            "enrolperiod" => 0,
            "enrolstartdate" => 0,
            "enrolenddate" => 0,
            "maxenrolled" => 0,
            "roleinstructor" => "3",
            "rolelearner" => "5",
            "provisioningmodeinstructor" => get_config('tool_ltiextensions', 'provisioningmodeinstructor'),
            "provisioningmodelearner" => get_config('tool_ltiextensions', 'provisioningmodelearner'),
            "gradesync" => "1",
            "gradesynccompletio," => "0",
            "membersync" => "1",
            "membersyncmode" => "1",
            "maildisplay" => "2",
            "city" => "",
            "country" => "",
            "timezone" => "99",
            "lang" => get_config('tool_ltiextensions', 'lang'),
            "institution" => "",
        ];
    }

    private function publish_courses($plugin) {
        $courses = courses_repository::get_not_published_courses();
        $published_courses = 0;
        foreach ($courses as $course) {
            if($course->id == SITEID){
                continue;
            }
            
            mtrace("Starting - Publishing course '$course->id' '$course->shortname' (contextid: '$course->contextid')");
            
            try {
                $fields = $this->get_fields($course->id, $course->contextid);
                
                $plugin->add_instance($course, $fields);
                
                $published_courses++;
            } catch (Throwable $e) {
                mtrace("Error while publishing course '$course->id' '$course->shortname' (contextid: '$course->contextid')");
                debug_utils::traceError($e);
            }
        }
     
        return $published_courses;
    }

    private function publish_modules($plugin) {
        global $DB;
        
        $modules = courses_repository::get_not_published_modules();
        $published_modules = 0;
        foreach ($modules as $module) {
            if($module->course == SITEID){
                continue;
            }
            
            $course = $DB->get_record('course', array('id' => $module->course), '*', MUST_EXIST);

            mtrace("Starting - Publishing module '$module->id' from course '$course->shortname' (contextid: '$module->contextid')");

            try {
                $fields = $this->get_fields($module->course, $module->contextid);
                
                $plugin->add_instance($course, $fields);
                
                $published_modules++;
            } catch (Throwable $e) {
                mtrace("Error while publishing module '$module->id' (contextid: '$module->contextid')");
                debug_utils::traceError($e);
            }
        }

        return $published_modules;
    }

    /**
     * Publishes all not published courses and their modules as LTI tools
     *
     * @return bool|void
     */
    public function execute() {
        $type = 'lti';
        $plugin = enrol_get_plugin($type);
        if (!$plugin) {
            throw new moodle_exception('invaliddata', 'error');
        }

        $published_courses = $this->publish_courses($plugin);
        $published_modules = $this->publish_modules($plugin);

        mtrace("Published {$published_courses} courses, {$published_modules} modules");
    }
}
