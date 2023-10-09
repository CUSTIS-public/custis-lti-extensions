<?php

namespace tool_ltiextensions\task;

use context_course;
use core\task\scheduled_task;
use Throwable;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\repository\courses_repository;

class test_api_task extends scheduled_task
{

    public function get_name()
    {
        return get_string('test_api_task', 'tool_ltiextensions');
    }

    public function execute()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/accesslib.php');
        require_once($CFG->libdir.'/gradelib.php');

        $started = time();
        mtrace('Starting job...');

        $courseid = 6347;
        $studentid = 589;
        $student_role_id = 5;
        $modid = 1434;
        // $context = context_course::instance(6347);
        // mtrace("Context id: ".$context->id); 

        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);

        $enrolplugin = enrol_get_plugin('manual');
        $enrolplugin->enrol_user($instance, $studentid, $student_role_id);

        $grading_info = grade_get_grades($courseid, 'mod', 'quiz', $modid, $studentid);
        mtrace('grading info: ');
        print_r($grading_info);

        $duration = time() - $started;
        mtrace('Job completed in: ' . $duration . ' seconds');
    }
}