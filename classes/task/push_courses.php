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
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\interop\curl_http_version_1_1;
use tool_ltiextensions\service\custis_lti_post_courses_service;
use tool_ltiextensions\repository\courses_repository;
use tool_ltiextensions\repository\customfield_repository;
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

    protected customfield_repository $customfield_repository;

    protected courses_repository $courses_repository;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('push_courses', 'tool_ltiextensions');
    }

    private function post_courses(
        custis_lti_post_courses_service $courseservice,
        array $moduleTypes,
        array $courses
    ) {
        $processedcourses = array();
        foreach ($courses as $course) {
            try {
                $data = array();
                $data['moduleTypes'] = $moduleTypes;
                $data['courses'] = [$course];
                $result = $courseservice->postCourses($data);
                if ($result['status'] != 200) {
                    mtrace("Failed to post course '" . $course['name'] . "' (" . $course['id'] . "). Status code " . $result['status']);
                }
                else {
                    $processedcourses[] = $course;
                }
            } catch (Throwable $e) {
                mtrace("Error while working with course '" . $course['name'] . "' (" . $course['id'] . ")");
                debug_utils::traceError($e);
            }
        }

        return $processedcourses;
    }

    protected function push_courses(
        array $moduleTypes,
        array $unpublishedCourses,
        array $modifiedCourses,
        LtiServiceConnector $sc,
        LtiRegistration $registration,
        deployment $deployment,
        $deploymentSettings,
        $customfieldid
    ) {
        $prefix = str_utils::ensureSlash($deploymentSettings->lmsapi);
        $linksUrl = new moodle_url($prefix . "save-courses", ['deploymentId' => $deployment->get_deploymentid()]);
        $servicedata = [
            'url' => $linksUrl->out(false)
        ];
        mtrace("URL to post courses: {$servicedata['url']}");
        $courseservice = new custis_lti_post_courses_service($sc, $registration, $servicedata);

        mtrace("Sending unpublished courses...");
        $processedUnpublishedCourses = $this->post_courses($courseservice, $moduleTypes, $unpublishedCourses);

        mtrace("Sending modified courses...");
        $processedModifiedCourses = $this->post_courses($courseservice, $moduleTypes, $modifiedCourses);

        $this->customfield_repository->update_course_publish_status($processedUnpublishedCourses, $processedModifiedCourses, $customfieldid);
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
        $this->customfield_repository = new customfield_repository();
        $this->courses_repository = new courses_repository();

        $sesscache = new launch_cache_session();
        $appregistrations = $this->appregistrationrepo->find_all();

        $customfieldname = "modeus_course_published";
        $customfielddescription = "Отметка о факте синхронизации курса Moodle с Modeus. Факт наличия кастомной записи указывает, что курс был опубликован.";
        $customfieldid = $this->customfield_repository->get_or_create_custom_field_id($customfieldname, $customfielddescription);

        mtrace("Retreiving module types");
        $moduleTypes = $this->courses_repository->get_module_types();
        mtrace("Found " . count($moduleTypes) . " module types");

        mtrace("Retreiving courses");
        $unpublishedCourses = $this->courses_repository->get_unpublished_courses($customfieldid);
        mtrace("Found " . count($unpublishedCourses) . " unpublished courses");

        $modifiedCourses = $this->courses_repository->get_modified_courses($customfieldid);
        mtrace("Found " . count($modifiedCourses) . " published and modified courses");

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

                    $this->push_courses($moduleTypes, $unpublishedCourses, $modifiedCourses, $sc, $registration, $deployment, $deploymentSettings, $customfieldid);

                    mtrace("Successfully pushed courses to $deploymentName");
                } catch (Throwable $e) {
                    mtrace("Error while working with platform $deploymentName");
                    debug_utils::traceError($e);
                }
            }
        }
    }
}
