<?php

namespace tool_ltiextensions\task;

use completion_info;
use Throwable;
use tool_ltiextensions\courses_consts;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\repository\courses_repository;
use tool_ltiextensions\task\base\base_sync_job;

class pull_courses extends base_sync_job
{
    public function get_name()
    {
        return 'pull_courses';
    }

    public function do_work(array $currentSession, ?array $lastClosedSession)
    {
        $categoryId = get_config('tool_ltiextensions', 'default_category');
        if (!$categoryId) {
            throw new \Exception("Error: Setting 'default_category' not set");
        }

        $prototypes = $this->lmsAdapterService->getCoursesToCreate($currentSession['id']);
        if (count($prototypes) != 0) {
            $created = $this->create_courses($prototypes, $categoryId);
            mtrace("Created " . count($created) . " courses");
        }
    }

    private function create_courses($courses, $categoryId)
    {
        global $CFG, $DB;
        require_once $CFG->dirroot . "/course/lib.php";
        require_once $CFG->libdir . '/completionlib.php';

        $getId = function ($course) {
            return $course['id'];
        };

        $idnumbers = array_map($getId, $courses);
        $existingCourses = courses_repository::get_courses_by_idnumbers($idnumbers);

        $resultcourses = array();
        foreach ($courses as $coursePrototype) {
            $fullname = $coursePrototype['name'];
            mtrace("");
            mtrace("Creating course [$fullname]");

            try {
                $idnumber = $coursePrototype['id'];
                if ($this->hasCourseWithIdNumber($existingCourses, $idnumber)) {
                    mtrace("Курс с IDNumber = [$idnumber] уже существует");
                    continue;
                }

                $course = $this->createCourse($coursePrototype, $categoryId);
                $transaction = $DB->start_delegated_transaction();
                $courseId = create_course((object) $course)->id;

                $this->create_sections($coursePrototype['sections'], $courseId);

                $resultcourses[] = array('id' => $courseId, 'shortname' => $course['shortname']);
                $transaction->allow_commit();

                mtrace("Created course [$fullname] ($courseId)");
            } catch (Throwable $e) {
                mtrace("Error creating course [$fullname]");
                debug_utils::traceError($e);
                $DB->force_transaction_rollback();
            }
        }

        return $resultcourses;
    }

    private function hasCourseWithIdNumber($courses, $idnumber)
    {
        foreach ($courses as $k) {
            if ($k->idnumber == $idnumber) {
                return true;
            }
        }

        return false;
    }

    private function createCourse($coursePrototype, $categoryId)
    {
        $course = array();

        if (completion_info::is_enabled_for_site()) {
            $course['enablecompletion'] = 1;
        } else {
            $course['enablecompletion'] = 0;
        }

        $course['idnumber'] = $coursePrototype['id'];
        $course['fullname'] = $coursePrototype['name'];
        $course['shortname'] = $coursePrototype['shortName'];
        $course['summary'] = $coursePrototype['summary'];
        $course['category'] = $categoryId;
        $course['lang'] = get_string_manager()->translation_exists('ru', false) ? 'ru' : 'en';
        $course['format'] = "topics";
        $course['showgrades'] = 1;
        $course['visible'] = 1;

        return $course;
    }

    private function create_sections($sections, $courseid)
    {
        foreach ($sections as $sectionProto) {
            $section = course_create_section($courseid);
            $name = $sectionProto['name'];
            course_update_section($courseid, $section, array('summary' => '', 'name' => $name));

            $this->create_modules($sectionProto['modules'], $courseid, $section->section);
        }
    }

    private function create_modules($modules, $courseid, $sectionid)
    {
        global $CFG;
        require_once $CFG->dirroot . '/mod/chat/lib.php';

        foreach ($modules as $moduleProto) {
            if (in_array($moduleProto['moduleTypeId'], courses_consts::$unsupported_module_types)) {
                mtrace("Создание элементов [" . $moduleProto['moduleTypeId'] . "] не поддерживается. Элемент [" . $moduleProto['name'] . "] не будет создан");
                continue;
            }

            try {
                $module = array();

                $module['modulename'] = $moduleProto['moduleTypeId'];
                $module['name'] = $moduleProto['name'];
                $module['course'] = $courseid;
                $module['section'] = $sectionid;
                $module['visible'] = 1;

                $module['cmidnumber'] = $moduleProto['id'];

                //заполняем поля, специфичные для того или иного элемента LMS
                $module['quizpassword'] = ''; // quiz
                $module['grade'] = 100; // workshop
                $module['gradinggrade'] = 100; // workshop
                $module['page_after_submit'] = ''; //feedback
                $module['displayformat'] = 'dictionary'; //glossary

                $module['template'] = 1; //survey

                $module['schedule'] = CHAT_SCHEDULE_NONE; //chat
                $module['chattime'] = time(); //chat

                $module['submissiondrafts'] = 0; //assigment
                $module['requiresubmissionstatement'] = 0; //assigment
                $module['sendnotifications'] = 0; //assigment
                $module['sendlatenotifications'] = 0; //assigment
                $module['duedate'] = 0; //assigment
                $module['cutoffdate'] = 0; //assigment
                $module['gradingduedate'] = 0; //assigment
                $module['allowsubmissionsfromdate'] = 0; //assigment
                $module['teamsubmission'] = 0; //assigment
                $module['requireallteammemberssubmit'] = 0; //assigment
                $module['blindmarking'] = 0; //assigment
                $module['hidegrader'] = 0; //assigment
                $module['revealidentities'] = 0; //assigment
                $module['attemptreopenmethod'] = 'none'; //assigment
                $module['maxattempts'] = -1; //assigment
                $module['markingworkflow'] = 0; //assigment
                $module['markingallocation'] = 0; //assigment
                $module['sendstudentnotifications'] = 1; //assigment
                $module['preventsubmissionnotingroup'] = 0; //assigment
                $module['activityformat'] = 0; //assigment
                $module['timelimit'] = 0; //assigment
                $module['submissionattachments'] = 0; //assigment

                $module['option'] = array("Добавьте варианты ответов"); //choiсe
                $module['strategy'] = 'accumulative'; //workshop

                $introtext = '';
                if ($moduleProto['moduleTypeId'] == 'label') {
                    $introtext = $moduleProto['name'];
                }
                $module['introeditor'] = array('text' => $introtext, 'format' => FORMAT_PLAIN, 'itemid' => IGNORE_FILE_MERGE);

                $module = create_module((object) $module);
            } catch (Throwable $e) {
                $type = $moduleProto['moduleTypeId'];
                $name = $moduleProto['name'];
                mtrace("Failed to create module $type [$name]");
                throw $e;
            }
        }
    }
}
