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

use completion_info;
use context_course;
use context_module;
use core\task\scheduled_task;
use enrol_lti\local\ltiadvantage\entity\deployment;
use enrol_lti\local\ltiadvantage\lib\http_client;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Exception;
use moodle_url;
use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiServiceConnector;
use Throwable;
use tool_ltiextensions\courses_consts;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\interop\custis_lti_courses_service;
use tool_ltiextensions\interop\custis_lti_pull_courses_service;
use tool_ltiextensions\repository\courses_repository;
use tool_ltiextensions\str_utils;

/**
 * Берет информацию о курсах из LMS-платформы и создает их в Мудл
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pull_courses extends scheduled_task
{
    /** @var deployment_repository $deploymentrepo for fetching deployment instances. */
    protected $deploymentrepo;

    /** @var application_registration_repository $appregistrationrepo for fetching application_registration instances.*/
    protected $appregistrationrepo;

    /** @var issuer_database $issuerdb library specific registration DB required to create service connectors.*/
    protected $issuerdb;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('pull_courses', 'tool_ltiextensions');
    }

    public function create_modules($modules, $courseid, $sectionid)
    {
        global $CFG;
        require_once($CFG->dirroot . '/mod/chat/lib.php');

        foreach ($modules as $moduleProto) {
            if (in_array($moduleProto['moduleTypeId'], courses_consts::$unsupported_module_types)) {
                mtrace("Создание элементов [" . $moduleProto['moduleTypeId'] . "] не поддерживается. Элемент [". $moduleProto['name'] ."] не будет создан");
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
                $module['quizpassword'] = ''; //quiz
                // TODO MDSINTLMS-2 Вынести в api
                $module['grade'] = 100; // workshop
                $module['gradinggrade'] = 100; //Что это такое? workshop
                $module['page_after_submit'] = ''; //feedback
                $module['displayformat'] = 'dictionary'; //glossary get_list_of_plugins('mod/glossary/formats','TEMPLATE');

                // TODO MDSINTLMS-2 Вынести в настройки / получать из БД
                $module['template'] = 1; //survey $DB->get_record("survey", array("id"=>$survey->template))

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

                $module = create_module((object)$module);
                mtrace("Created module [$module->name] ($module->modulename, $module->module)");
            } catch (Throwable $e) {
                $type = $moduleProto['moduleTypeId'];
                $name = $moduleProto['name'];
                mtrace("Failed to create module $type [$name]");
                throw $e;
            }
        }
    }

    public function create_sections($sections, $courseid)
    {
        foreach ($sections as $sectionProto) {
            $section = course_create_section($courseid);
            $name = $sectionProto['name'];
            course_update_section($courseid, $section, array('summary' => '', 'name' => $name));
            mtrace("Created section [$name] ($section->id)");

            $this->create_modules($sectionProto['modules'], $courseid, $section->section);
        }
    }

    function hasCourseWithIdNumber($courses, $idnumber)
    {
        foreach ($courses as $k) {
            if ($k->idnumber == $idnumber) {
                return true;
            }
        }

        return false;
    }

    function hasCourseWithShortName($courses, $shortname)
    {
        foreach ($courses as $k) {
            if ($k->shortname == $shortname) {
                return true;
            }
        }

        return false;
    }

    public function create_courses($courses, $categoryId)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
        require_once($CFG->libdir . '/completionlib.php');

        $getId = function ($course) {
            return $course['id'];
        };

        $getShortName = function ($course) {
            return $course['shortName'];
        };

        $idnumbers = array_map($getId, $courses);
        $existingCourses1 = courses_repository::get_courses_by_idnumbers($idnumbers);
        $shortNames = array_map($getShortName, $courses);
        $existingCourses2 = courses_repository::get_courses_by_shortnames($shortNames);

        $resultcourses = array();

        foreach ($courses as $coursePrototype) {
            $fullname = $coursePrototype['name'];
            mtrace("");
            mtrace("Creating course [$fullname]");

            try {
                $idnumber = $coursePrototype['id'];
                if ($this->hasCourseWithIdNumber($existingCourses1, $idnumber)) {
                    mtrace("Курс с IDNumber = [$idnumber] уже существует");
                    continue;
                }

                $shortName = $coursePrototype['shortName'];
                if ($this->hasCourseWithShortName($existingCourses2, $shortName)) {
                    mtrace("Курс с shortname = [$shortName] уже существует");
                    continue;
                }

                $transaction = $DB->start_delegated_transaction();
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

                $course['id'] = create_course((object) $course)->id;

                $id = $course['id'];

                $this->create_sections($coursePrototype['sections'], $id);

                $resultcourses[] = array('id' => $course['id'], 'shortname' => $course['shortname']);
                $transaction->allow_commit();

                mtrace("Created course [$fullname] ($id)");
            } catch (Throwable $e) {
                mtrace("Error creating course [$fullname]");
                debug_utils::traceError($e);
                $DB->force_transaction_rollback();
            }
        }

        return $resultcourses;
    }


    protected function pull_courses(
        LtiServiceConnector $sc,
        LtiRegistration $registration,
        deployment $deployment,
        $deploymentSettings
    ): array {
        $prefix = str_utils::ensureSlash($deploymentSettings->lmsapi);
        $linksUrl = new moodle_url($prefix . "get-courses-to-create", ['deploymentId' => $deployment->get_deploymentid()]);
        $servicedata = [
            'url' => $linksUrl->out(false)
        ];
        $linksservice = new custis_lti_pull_courses_service($sc, $registration, $servicedata);

        return $linksservice->getCourses();
    }


    /**
     * Берет информацию о курсах из LMS-платформы и создает их в Мудл
     * @return bool|void
     */
    public function execute()
    {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/externallib.php');

        $this->appregistrationrepo = new application_registration_repository();
        $this->deploymentrepo = new deployment_repository();
        $this->issuerdb = new issuer_database($this->appregistrationrepo, $this->deploymentrepo);

        $sesscache = new launch_cache_session();
        $appregistrations = $this->appregistrationrepo->find_all();

        // Формат: { 'deploymentid': { 'lmsapi': 'url' }}
        $platformSettings = json_decode(get_config('tool_ltiextensions', 'platform_settings'));

        $categoryId = get_config('tool_ltiextensions', 'default_category');
        if (!$categoryId) {
            throw "Не задана настройка default_category";
        }

        foreach ($appregistrations as $appregistration) {
            $deployments = $this->deploymentrepo->find_all_by_registration($appregistration->get_id());
            foreach ($deployments as $deployment) {
                $deploymentName = "platform/deployment '" . $appregistration->get_name() . "' ('" . $appregistration->get_id() . "')/'" . $deployment->get_deploymentname() . "' (" . $deployment->get_deploymentid() . ")";
                try {
                    mtrace("");

                    $deplid = $deployment->get_deploymentid();
                    $deploymentSettings = $platformSettings->$deplid;
                    if (!$deploymentSettings) {
                        mtrace("Skipping $deploymentName, because no settings set in tool_ltiextensions | platform_settings");
                        continue;
                    }
                    if (!$deploymentSettings->lmsapi) {
                        mtrace("Skipping $deploymentName, because 'lmsapi' not set in tool_ltiextensions | platform_settings");
                        continue;
                    }

                    mtrace("Starting sync with $deploymentName");

                    $registration = $this->issuerdb->findRegistrationByIssuer(
                        $appregistration->get_platformid()->out(false),
                        $appregistration->get_clientid()
                    );
                    $sc = new LtiServiceConnector($sesscache, new http_client(new \curl()));

                    $courses = $this->pull_courses($sc, $registration, $deployment, $deploymentSettings);

                    mtrace("Found " . count($courses) . " to create ($deploymentName)");

                    $created = $this->create_courses($courses, $categoryId);
                    mtrace("Created " . count($created) . " courses");
                    mtrace("Successfully pulled courses from $deploymentName");
                } catch (Throwable $e) {
                    mtrace("Error while working with $deploymentName");
                    debug_utils::traceError($e);
                }
            }
        }
    }
}
