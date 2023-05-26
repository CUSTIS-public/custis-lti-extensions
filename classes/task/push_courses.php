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

namespace tool_ltiextensions\task;

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
use tool_ltiextensions\interop\curl_http_version_1_1;
use tool_ltiextensions\interop\custis_lti_courses_service;
use tool_ltiextensions\str_utils;

/**
 * Отправляет информацию в LMS о курсах и их модулях.
 * Отправляет информацию только о курсах и модулях, опубликованных как LTI Tool.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_courses extends scheduled_task
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
        return get_string('push_courses', 'tool_ltiextensions');
    }

    protected function push_courses(
        array $moduleTypes,
        array $courses,
        LtiServiceConnector $sc,
        LtiRegistration $registration,
        deployment $deployment,
        $deploymentSettings
    ) {
        $prefix = str_utils::ensureSlash($deploymentSettings->lmsapi);
        $linksUrl = new moodle_url($prefix . "save-courses", ['deploymentId' => $deployment->get_deploymentid()]);
        $servicedata = [
            'url' => $linksUrl->out(false)
        ];
        $courseservice = new custis_lti_courses_service($sc, $registration, $servicedata);

        // Бежим в цикле по курсам, поскольку при отправке всех курсов пачкой возникают проблемы
        // Это надо оптимизировать в рамках MODEUSSW-19325
        foreach ($courses as $course) {
            try {
                $data = array();
                $data['moduleTypes'] = $moduleTypes;
                $data['courses'] = [$course];
                $result = $courseservice->postCourses($data);
                if ($result['status'] != 200) {
                    mtrace("Failed to post course '" . $course['name'] . "' (" . $course['id'] . "). Status code " . $result['status']);
                }
            } catch (Throwable $e) {
                mtrace("Error while working with course '" . $course['name'] . "' (" . $course['id'] . ")");
                debug_utils::traceError($e);
            }
        }
    }

    public function get_course_modules($course)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $modinfosections = $modinfo->get_sections();
        $modules = array();

        mtrace("Getting course modules [$course->fullname] ($course->id)");

        foreach ($sections as $key => $section) {
            foreach ($modinfosections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];

                $module = array();

                $modcontext = context_module::instance($cm->id);

                $lti = $DB->get_records('enrol_lti_tools', array('contextid' => $modcontext->id), 'uuid', 'uuid', 0, 1);
                reset($lti);
                $firstlti = current($lti);

                if (count($lti) == 0) {
                    continue;
                }

                $module['id'] = $cm->id;
                $module['lmsIdNumber'] = $cm->idnumber;
                $module['name'] = external_format_string($cm->name, $modcontext->id);
                $module['customLtiProperties'] = "id=$firstlti->uuid";
                $module['moduleTypeId'] = $cm->modname;

                $modules[] = $module;
            }
        }

        return $modules;
    }

    public function get_courses()
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        //retrieve courses
        $courses = $DB->get_records('course');

        //create return value
        $coursesinfo = array();
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $context = context_course::instance($course->id, IGNORE_MISSING);
            $lti = $DB->get_records('enrol_lti_tools', array('contextid' => $context->id), 'uuid', 'uuid', 0, 1);

            if (count($lti) == 0) {
                continue;
            }

            reset($lti);
            $firstlti = current($lti);

            $courseinfo = array();
            $courseinfo['id'] = $course->id;
            $courseinfo['lmsIdNumber'] = $course->idnumber;
            $courseinfo['name'] = external_format_string($course->fullname, $context->id);
            $courseinfo['customLtiProperties'] = "id=$firstlti->uuid";
            $courseinfo['modules'] = $this->get_course_modules($course);

            $coursesinfo[] = $courseinfo;
        }

        return $coursesinfo;
    }

    public function get_module_types()
    {
        $lang = force_current_language('ru');
        try {
            global $CFG, $DB;
            require_once($CFG->dirroot . "/course/lib.php");

            $types = $DB->get_records('modules', array('visible' => true));

            //create return value
            $typesinfo = array();
            foreach ($types as $moduleType) {
                $moduleTypeinfo = array();
                $moduleTypeinfo['id'] = $moduleType->name;
                $moduleTypeinfo['name'] = $this->get_module_label($moduleType->name);
                $moduleTypeinfo['canCreate'] = !in_array($moduleType->name, courses_consts::$unsupported_module_types);

                $typesinfo[] = $moduleTypeinfo;
            }

            return $typesinfo;
        } finally {
            force_current_language($lang);
        }
    }

    private static function get_module_label(string $modulename): string
    {
        if (get_string_manager()->string_exists('modulename', $modulename)) {
            $modulename = get_string('modulename', $modulename);
        }

        return $modulename;
    }

    /**
     * Отправляет информацию в LMS о курсах и их модулях.
     * Отправляет информацию только о курсах и модулях, опубликованных как LTI Tool.
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

        mtrace("Retreiving module types");
        $moduleTypes = $this->get_module_types();
        mtrace("Found " . count($moduleTypes) . " module types");

        mtrace("Retreiving courses");
        $courses = $this->get_courses();
        mtrace("Found " . count($courses) . " courses");

        // Формат: { 'deploymentid': { 'lmsapi': 'url' }}
        $platformSettings = json_decode(get_config('tool_ltiextensions', 'platform_settings'));

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
                    $sc = new LtiServiceConnector($sesscache, new http_client(new curl_http_version_1_1()));

                    $this->push_courses($moduleTypes, $courses, $sc, $registration, $deployment, $deploymentSettings);

                    mtrace("Successfully pushed courses to $deploymentName");
                } catch (Throwable $e) {
                    mtrace("Error while working with platform $deploymentName");
                    debug_utils::traceError($e);
                }
            }
        }
    }
}
