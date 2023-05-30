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
use core_user;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\deployment;
use enrol_lti\local\ltiadvantage\lib\http_client;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use moodle_url;
use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiServiceConnector;
use Throwable;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\interop\custis_lti_links_service;
use tool_ltiextensions\interop\LtiMessageLaunchProxy;
use tool_ltiextensions\str_utils;

use function tool_ltiextensions\endsWith;

/**
 * Запускает синхронизацию участников курсов и оценок через LTI.
 * В классическом LTI для этого требуется, чтобы в курс зашел один из пользователей,
 * однако джоб преодолевает это ограничение.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_lti_sync extends scheduled_task
{
    /** @var deployment_repository $deploymentrepo for fetching deployment instances. */
    protected $deploymentrepo;

    /** @var application_registration_repository $appregistrationrepo for fetching application_registration instances.*/
    protected $appregistrationrepo;

    /** @var issuer_database $issuerdb library specific registration DB required to create service connectors.*/
    protected $issuerdb;

    private $toollaunchservice;

    private $user;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('start_lti_sync', 'tool_ltiextensions');
    }

    protected function get_links(
        LtiServiceConnector $sc,
        LtiRegistration $registration,
        deployment $deployment,
        $deploymentSettings
    ) {
        $prefix = str_utils::ensureSlash($deploymentSettings->lmsapi);
        $linksUrl = new moodle_url($prefix . "getlinks", ['deploymentId' => $deployment->get_deploymentid()]);
        $servicedata = [
            'context_links_url' => $linksUrl->out(false)
        ];
        $linksservice = new custis_lti_links_service($sc, $registration, $servicedata);

        return $linksservice->getLinks();
    }

    protected function process_links(
        application_registration $appregistration,
        array $links
    ): int {
        $linksProcessed = 0;
        foreach ($links as $link) {
            try {
                $link['iss'] = $appregistration->get_platformid()->out(false);
                $link['aud'] = $appregistration->get_clientid();

                $msg = new LtiMessageLaunchProxy($link);

                // Эмулируем запуск курса пользователем. Это создаст записи в нужных таблицах, после чего Moodle будет синхронизировать участников курсов и оценки
                $this->toollaunchservice->user_launches_tool($this->user, $msg);
                $linksProcessed++;
            } catch (Throwable $e) {
                $resourceLinkId = $link['https://purl.imsglobal.org/spec/lti/claim/resource_link']['id'];
                $resourceLinkTitle = $link['https://purl.imsglobal.org/spec/lti/claim/resource_link']['title'];
                mtrace("Error while working with link '$resourceLinkTitle' ('$resourceLinkId')");
                debug_utils::traceError($e);
            }
        }

        return $linksProcessed;
    }

    /**
     * Запускает синхронизацию участников курсов и оценок через LTI.
     * В классическом LTI для этого требуется, чтобы в курс зашел один из пользователей,
     * однако джоб преодолевает это ограничение.
     *
     * @return bool|void
     */
    public function execute()
    {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $this->appregistrationrepo = new application_registration_repository();
        $this->deploymentrepo = new deployment_repository();
        $this->issuerdb = new issuer_database($this->appregistrationrepo, $this->deploymentrepo);

        $this->user = core_user::get_user(get_config('tool_ltiextensions', 'sync_user'), '*', MUST_EXIST);

        $this->toollaunchservice = new tool_launch_service(
            new deployment_repository(),
            $this->appregistrationrepo,
            new resource_link_repository(),
            new user_repository(),
            new context_repository()
        );

        $sesscache = new launch_cache_session();
        $appregistrations = $this->appregistrationrepo->find_all();

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
                    $sc = new LtiServiceConnector($sesscache, new http_client(new \curl()));

                    mtrace("Getting links from platform $deploymentName");
                    $links = $this->get_links($sc, $registration, $deployment, $deploymentSettings);
                    mtrace("Received " . count($links) . " links");

                    $linksProcessed = $this->process_links($appregistration, $links);

                    mtrace("Processed $linksProcessed links from " . count($links));
                } catch (Throwable $e) {
                    mtrace("Error while working with platform $deploymentName");
                    debug_utils::traceError($e);
                }
            }
        }
    }
}
