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

namespace tool_ltiextensions\task;

use core\task\scheduled_task;
use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\nrps_info;
use enrol_lti\local\ltiadvantage\entity\resource_link;
use enrol_lti\local\ltiadvantage\entity\user;
use enrol_lti\local\ltiadvantage\lib\http_client;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use moodle_url;
use Packback\Lti1p3\LtiNamesRolesProvisioningService;
use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiServiceConnector;
use tool_ltiextensions\interop\curl_http_version_1_1;
use tool_ltiextensions\repository\custom_context_repository;
use tool_ltiextensions\repository\custom_deployment_repository;
use tool_ltiextensions\repository\custom_resource_link_repository;
use tool_ltiextensions\repository\customfield_repository;
use tool_ltiextensions\service\audit_event_logging_service;
use stdClass;
use tool_ltiextensions\str_utils;

/**
 * Модифицированная копия фоновой задачи sync_members из плагина "Publish as LTI Tool".
 * Все локальные данные загружает разом (ресурсы, ссылки, контексты), и загружает из Модеус участников курса в рамках одного контекста (=РМУПа), копируя их на все Resource Link.
 * Такое поведение отходит от стандарта и ломает контроль доступа на уровне ссылок (который мы не используем), однако в ситуации "огромное количество курсов, элементов и участников" - оправдано.
 * 
 * Отличия от оригинала:
 * - Переписан метод execute()
 * - Закомментированы строчки с излишним логированием (на момент разработки mtrace почему-то игнорирует для задач настройку логирования и логирует все подряд)
 * - Внутри execute() используется кастомный sync_members_repository, который загружает данные, для которых не нашлось API в существующих репозиториях. (нужно было получение всего и сразу, а не по id)
 */
class sync_members_extended extends scheduled_task
{

    /** @var array Array of user photos. */
    protected $userphotos = [];

    /** @var resource_link_repository $resourcelinkrepo for fetching resource_link instances.*/
    protected $resourcelinkrepo;

    /** @var application_registration_repository $appregistrationrepo for fetching application_registration instances.*/
    protected $appregistrationrepo;

    /** @var deployment_repository $deploymentrepo for fetching deployment instances. */
    protected $deploymentrepo;

    /** @var user_repository $userrepo for fetching and saving lti user information.*/
    protected $userrepo;

    /** @var issuer_database $issuerdb library specific registration DB required to create service connectors.*/
    protected $issuerdb;

    protected custom_context_repository $custom_context_repository;

    protected custom_deployment_repository $custom_deployment_repository;

    protected custom_resource_link_repository $custom_resource_link_repository;

    protected customfield_repository $customfield_repository;

    /**
     * Get the name for this task.
     *
     * @return string the name of the task.
     */
    public function get_name(): string
    {
        return get_string('tasksyncmembers', 'enrol_lti');
    }

    /**
     * Make a resource-link-level memberships call.
     *
     * @param nrps_info $nrps information about names and roles service endpoints and scopes.
     * @param LtiServiceConnector $sc a service connector object.
     * @param LtiRegistration $registration the registration
     * @param resource_link $resourcelink the resource link
     * @return array an array of members if found.
     */
    protected function get_resource_link_level_members(
        nrps_info $nrps, LtiServiceConnector $sc, LtiRegistration $registration,
        resource_link $resourcelink, string $customfieldid
    ) {

        // Try a resource-link-level memberships call first, falling back to context-level if no members are found.
        $reslinkmembershipsurl = $nrps->get_context_memberships_url();
        $reslinkmembershipsurl->param('rlid', $resourcelink->get_resourcelinkid());
        $reslinkmembershipsurl->param('timePassed', $this->customfield_repository->get_lticontext_membership_passed_sync_time($customfieldid, $resourcelink->get_contextid()));
        $servicedata = [
            'context_memberships_url' => $reslinkmembershipsurl->out(false)
        ];
        $reslinklevelnrps = new LtiNamesRolesProvisioningService($sc, $registration, $servicedata);

        mtrace('Making resource-link-level memberships request');
        return $reslinklevelnrps->getMembers();
    }

    /**
     * Make a context-level memberships call.
     *
     * @param nrps_info $nrps information about names and roles service endpoints and scopes.
     * @param LtiServiceConnector $sc a service connector object.
     * @param LtiRegistration $registration the registration
     * @return array an array of members.
     */
    protected function get_context_level_members(
        nrps_info $nrps, LtiServiceConnector $sc, LtiRegistration $registration,
        string $customfieldid, string $contextid
    ) {
        $url = $nrps->get_context_memberships_url();
        $url->param('timePassed', $this->customfield_repository->get_lticontext_membership_passed_sync_time($customfieldid, $contextid));

        $clservicedata = [
            'context_memberships_url' => $url->out(false)
        ];
        $contextlevelnrps = new LtiNamesRolesProvisioningService($sc, $registration, $clservicedata);

        return $contextlevelnrps->getMembers();
    }

    /**
     * Make the NRPS service call and fetch members based on the given resource link.
     *
     * Memberships will be retrieved by first trying the link-level memberships service first, falling back to calling
     * the context-level memberships service only if the link-level call fails.
     *
     * @param application_registration $appregistration an application registration instance.
     * @param resource_link $resourcelink a resourcelink instance.
     * @return array an array of members.
     */
    protected function get_members_from_resource_link(
        application_registration $appregistration,
        resource_link $resourcelink,
        string $customfieldid,
        string $contextid
    ) {

        // Get a service worker for the corresponding application registration.
        $registration = $this->issuerdb->findRegistrationByIssuer(
            $appregistration->get_platformid()->out(false),
            $appregistration->get_clientid()
        );
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $sc = new LtiServiceConnector(new launch_cache_session(), new http_client(new \curl()));

        $nrps = $resourcelink->get_names_and_roles_service();
        try {
            $members = $this->get_resource_link_level_members($nrps, $sc, $registration, $resourcelink, $customfieldid);
        } catch (\Exception $e) {
            mtrace('Link-level memberships request failed. Making context-level memberships request');
            $members = $this->get_context_level_members($nrps, $sc, $registration, $customfieldid, $contextid);
        }

        return $members;
    }

    protected function confirm_membership_sync($lticontextid, $sc, $registration, $deployment, $deploymentSettings): void
    {
        $prefix = str_utils::ensureSlash($deploymentSettings->lmsapi);
        $linksUrl = new moodle_url($prefix . "confirm-membership-sync/$lticontextid", ['deploymentId' => $deployment->get_deploymentid()]);
        $servicedata = [
            'url' => $linksUrl->out(false)
        ];
        $audit_event_service = new audit_event_logging_service($sc, $registration, $servicedata);

        try {
            $audit_event_service->confirm_membership_sync();
        } catch (\Exception $e) {
            mtrace("Couldn't confirm membership sync, skipping. Error message: {$e->getMessage()}");
        }
    }

    /**
     * Performs the synchronisation of members.
     */
    public function execute()
    {
        if (!is_enabled_auth('lti')) {
            mtrace('Skipping task - ' . get_string('pluginnotenabled', 'auth', get_string('pluginname', 'auth_lti')));
            return;
        }
        if (!enrol_is_enabled('lti')) {
            mtrace('Skipping task - ' . get_string('enrolisdisabled', 'enrol_lti'));
            return;
        }

        // Запускаем линковку пользователей перед синхронизацией участников, чтобы незалинкованные участники не потерялись (не ждали изменений в РМУПе)
        $link_users_task = new auto_link_users();
        $link_users_task->execute();
        mtrace('Completed: auto_link_users task');
        mtrace("");

        $this->resourcelinkrepo = new resource_link_repository();
        $this->appregistrationrepo = new application_registration_repository();
        $this->deploymentrepo = new deployment_repository();
        $this->userrepo = new user_repository();
        $this->issuerdb = new issuer_database($this->appregistrationrepo, $this->deploymentrepo);
        $this->custom_context_repository = new custom_context_repository();
        $this->custom_deployment_repository = new custom_deployment_repository();
        $this->custom_resource_link_repository = new custom_resource_link_repository();
        $this->customfield_repository = new customfield_repository();
        $sesscache = new launch_cache_session();

        // Создаем customfield для сохранения даты синхронизации каждого курса
        $customfieldid = $this->customfield_repository->get_or_create_custom_field_id("modeus_course_membership_sync_time", "Дата последней синхронизации участников курсов с Modeus.");

        // Загружаем данные из БД Moodle
        $resources = helper::get_lti_tools([
            'status' => ENROL_INSTANCE_ENABLED,
            'membersync' => 1,
            'ltiversion' => 'LTI-1p3'
        ]);
        $count = count($resources);
        mtrace("Loaded resources ($count)");
        $registrations = $this->appregistrationrepo->find_all();
        $count = count($registrations);
        mtrace("Loaded registrations ($count)");
        $resourceLinks = $this->custom_resource_link_repository->find_all_resource_links();
        $count = count($resourceLinks);
        mtrace("Loaded resource links ($count)");
        $lticontexts = $this->custom_context_repository->find_all_lti_contexts();
        $count = count($lticontexts);
        mtrace("Loaded lticontexts ($count)");
        $deployments = $this->custom_deployment_repository->find_all_deployments();
        $count = count($deployments);
        mtrace("Loaded deployments ($count)");

        // Создаем словари для работы
        foreach ($resources as $resource) {
            $resourceById[$resource->id] = $resource;
        }
        foreach ($registrations as $registration) {
            $appregistrationsById[$registration->get_id()] = $registration;
        }
        foreach ($resourceLinks as $resourceLink) {
            $resourceLinksByContextId[$resourceLink->get_contextid()][] = $resourceLink;
        }
        foreach ($lticontexts as $lticontext) {
            $lticontextById[$lticontext->get_id()] = $lticontext;
        }
        foreach ($deployments as $deployment) {
            $deploymentById[$deployment->get_id()] = $deployment;
        }

        $usercount = 0;
        $enrolcount = 0;
        $unenrolcount = 0;
        $syncedusers = [];
        $linkcontextcount = count($resourceLinksByContextId);
        $contextcount = 0;
        // Группируем каждый resource link по lticontextid
        foreach ($resourceLinksByContextId as $lticontextid => $contextlinks) {
            $len = count($contextlinks);
            $contextcount = $contextcount + 1;
            $context = $lticontextById[$lticontextid];
            $lticontextexternalid = $context->get_contextid();
            mtrace("\nSyncing members for contextid $lticontextexternalid ($len links) ($contextcount from $linkcontextcount)");

            $members = null;

            foreach ($contextlinks as $link) {
                $resourceid = $link->get_resourceid();
                $linkid = $link->get_id();
                $resource = $resourceById[$resourceid];
                $deploymentid = $link->get_deploymentid();
                $deployment = $deploymentById[$deploymentid];
                $appregistrationid = $deployment->get_registrationid();
                $appregistration = $appregistrationsById[$appregistrationid];

                if ($resource == null || $appregistration == null || $deployment == null) {
                    mtrace("Skipping resource $resourceid for $linkid (not found or didn't match: contextid $lticontextexternalid, appregistration $appregistrationid, deployment $deploymentid");
                    continue;
                }

                // Загружаем участников в рамках РМУПа один раз, обобщаем полученные данные на все resource link
                if ($members === null) {
                    try {
                        $members = $this->get_members_from_resource_link($appregistration, $link, $customfieldid, $lticontextid);
                        $membercount = count($members);
                        mtrace("$membercount members received.");
                        $usercount += $membercount;
                    } catch (\Exception $e) {
                        mtrace("Skipping - Names and Roles service request failed: {$e->getMessage()}.");
                        $members = []; // если для одной ссылки не получилось, для остальных в рамках того же РМУПа получать данные бессмысленно
                        continue;
                    }
                } else if (count($members) == 0) {
                    continue;
                }

                // Process member information.
                [$rlenrolcount, $userids] = $this->sync_member_information(
                    $appregistration,
                    $resource,
                    $link,
                    $members
                );
                $enrolcount += $rlenrolcount;

                // Update the list of users synced for this shared resource or its context.
                $syncedusers = array_unique(array_merge($syncedusers, $userids));

                // Sync unenrolments on a per-resource-link basis so we have fine grained control over unenrolments.
                // If a resource link doesn't support NRPS, it will already have been skipped.
                $unenrolcount += $this->sync_unenrol_resourcelink($link, $resource, $syncedusers);
            }

            if (count($members) != 0) {
                $this->customfield_repository->save_lticontext_membership_sync_time($customfieldid, $lticontextid);
                $sc = new LtiServiceConnector($sesscache, new http_client(new curl_http_version_1_1()));
                $platformSettings = json_decode(get_config('tool_ltiextensions', 'platform_settings'));
                $deplid = $deployment->get_deploymentid();
                $deploymentSettings = $platformSettings->$deplid;
                $ltiregistration = $this->issuerdb->findRegistrationByIssuer(
                    $appregistration->get_platformid()->out(false),
                    $appregistration->get_clientid()
                );
                $this->confirm_membership_sync($lticontextexternalid, $sc, $ltiregistration, $deployment, $deploymentSettings);
            }

            $members = null; // Сбросим участников перед обработкой следующего РМУПа
        }

        mtrace("Completed - Processed $usercount users; enrolled $enrolcount members; unenrolled $unenrolcount members.\n");

        if (!empty($resources) && !empty($this->userphotos)) {
            // Sync the user profile photos.
            mtrace("Started - Syncing user profile images.");
            $countsyncedimages = $this->sync_profile_images();
            mtrace("Completed - Synced $countsyncedimages profile images.");
        }
    }

    /**
     * Process unenrolment of users for a given resource link and based on the list of recently synced users.
     *
     * @param resource_link $resourcelink the resource_link instance to which the $synced users pertains
     * @param stdClass $resource the resource object instance
     * @param array $syncedusers the array of recently synced users, who are not to be unenrolled.
     * @return int the number of unenrolled users.
     */
    protected function sync_unenrol_resourcelink(
        resource_link $resourcelink, stdClass $resource,
        array $syncedusers
    ): int {

        if (!$this->should_sync_unenrol($resource->membersyncmode)) {
            return 0;
        }
        $ltiplugin = enrol_get_plugin('lti');
        $unenrolcount = 0;

        // Get all users for the resource_link instance.
        $linkusers = $this->userrepo->find_by_resource_link($resourcelink->get_id());

        foreach ($linkusers as $ltiuser) {
            if (!in_array($ltiuser->get_localid(), $syncedusers)) {
                $instance = new stdClass();
                $instance->id = $resource->enrolid;
                $instance->courseid = $resource->courseid;
                $instance->enrol = 'lti';
                $ltiplugin->unenrol_user($instance, $ltiuser->get_localid());
                $unenrolcount++;
            }
        }
        return $unenrolcount;
    }

    /**
     * Check whether the member has an instructor role or not.
     *
     * @param array $member
     * @return bool
     */
    protected function member_is_instructor(array $member): bool
    {
        // See: http://www.imsglobal.org/spec/lti/v1p3/#role-vocabularies.
        $memberroles = $member['roles'];
        if ($memberroles) {
            $adminroles = [
                'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator',
                'http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator'
            ];
            $staffroles = [
                'http://purl.imsglobal.org/vocab/lis/v2/membership#ContentDeveloper',
                'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
                'http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor#TeachingAssistant',
                'ContentDeveloper',
                'Instructor',
                'Instructor#TeachingAssistant'
            ];
            $instructorroles = array_merge($adminroles, $staffroles);

            foreach ($instructorroles as $validrole) {
                if (in_array($validrole, $memberroles)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Method to determine whether to sync unenrolments or not.
     *
     * @param int $syncmode The shared resource's membersyncmode.
     * @return bool true if unenrolment should be synced, false if not.
     */
    protected function should_sync_unenrol($syncmode): bool
    {
        return $syncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL || $syncmode == helper::MEMBER_SYNC_UNENROL_MISSING;
    }

    /**
     * Method to determine whether to sync enrolments or not.
     *
     * @param int $syncmode The shared resource's membersyncmode.
     * @return bool true if enrolment should be synced, false if not.
     */
    protected function should_sync_enrol($syncmode): bool
    {
        return $syncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL || $syncmode == helper::MEMBER_SYNC_ENROL_NEW;
    }

    /**
     * Creates an lti user object from a member entry.
     *
     * @param stdClass $user the Moodle user record representing this member.
     * @param stdClass $resource the locally published resource record, used for setting user defaults.
     * @param resource_link $resourcelink the resource_link instance.
     * @param array $member the member information from the NRPS service call.
     * @return user the lti user instance.
     */
    protected function ltiuser_from_member(
        stdClass $user, stdClass $resource,
        resource_link $resourcelink, array $member
    ): user {

        if (!$ltiuser = $this->userrepo->find_single_user_by_resource($user->id, $resource->id)) {
            // New user, so create them.
            $ltiuser = user::create(
                $resourcelink->get_resourceid(),
                $user->id,
                $resourcelink->get_deploymentid(),
                $member['user_id'],
                $resource->lang,
                $resource->timezone,
                $resource->city ?? '',
                $resource->country ?? '',
                $resource->institution ?? '',
                $resource->maildisplay
            );
        }
        $ltiuser->set_lastaccess(time());
        return $ltiuser;
    }

    /**
     * Performs synchronisation of member information and enrolments.
     *
     * @param application_registration $appregistration the application_registration instance.
     * @param stdClass $resource the enrol_lti_tools resource information.
     * @param resource_link $resourcelink the resource_link instance.
     * @param user[] $members an array of members to sync.
     * @return array An array containing the counts of enrolled users and a list of userids.
     */
    protected function sync_member_information(
        application_registration $appregistration, stdClass $resource,
        resource_link $resourcelink, array $members
    ): array {

        $enrolcount = 0;
        $userids = [];

        // Get the verified legacy consumer key, if mapped, from the resource link's tool deployment.
        // This will be used to locate legacy user accounts and link them to LTI 1.3 users.
        // A launch must have been made in order to get the legacy consumer key from the lti1p1 migration claim.
        $deployment = $this->deploymentrepo->find($resourcelink->get_deploymentid());
        $legacyconsumerkey = $deployment->get_legacy_consumer_key() ?? '';

        foreach ($members as $member) {
            $auth = get_auth_plugin('lti');
            if ($auth->get_user_binding($appregistration->get_platformid()->out(false), $member['user_id'])) {
                // Use is bound already, so we can update them.
                $user = $auth->find_or_create_user_from_membership($member, $appregistration->get_platformid()->out(false));
                if ($user->auth != 'lti') {
                    // mtrace("Skipped profile sync for user '$user->id'. The user does not belong to the LTI auth method."); 
                }
            } else {
                // Not bound, so defer to the role-based provisioning mode for the resource.
                $provisioningmode = $this->member_is_instructor($member) ? $resource->provisioningmodeinstructor :
                    $resource->provisioningmodelearner;
                switch ($provisioningmode) {
                    case \auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY:
                        // Automatic provisioning - this will create a user account and log the user in.
                        $user = $auth->find_or_create_user_from_membership(
                            $member, $appregistration->get_platformid()->out(false),
                            $legacyconsumerkey
                        );
                        break;
                    case \auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING:
                    case \auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY:
                    default:
                        mtrace("Skipping account creation for member '{$member['user_id']}'. This member is not eligible for " .
                            "automatic creation due to the current account provisioning mode.");
                        continue 2;
                }
            }

            $ltiuser = $this->ltiuser_from_member($user, $resource, $resourcelink, $member);

            if ($this->should_sync_enrol($resource->membersyncmode)) {

                $ltiuser->set_resourcelinkid($resourcelink->get_id());
                $ltiuser = $this->userrepo->save($ltiuser);
                if ($user->auth != 'lti') {
                    // mtrace("Skipped picture sync for user '$user->id'. The user does not belong to the LTI auth method."); 
                } else {
                    if (isset($member['picture'])) {
                        $this->userphotos[$ltiuser->get_localid()] = $member['picture'];
                    }
                }

                // Enrol the user in the course.
                if (helper::enrol_user($resource, $ltiuser->get_localid()) === helper::ENROLMENT_SUCCESSFUL) {
                    $isinstructor = $this->member_is_instructor($member);
                    $roleid = $isinstructor ? $resource->roleinstructor : $resource->rolelearner;
                    role_assign($roleid, $ltiuser->get_localid(), $resource->contextid);
                    $enrolcount++;
                }
            }

            // If the member has been created, or exists locally already, mark them as valid so as to not unenrol them
            // when syncing memberships for shared resources configured as either MEMBER_SYNC_ENROL_AND_UNENROL or
            // MEMBER_SYNC_UNENROL_MISSING.
            $userids[] = $user->id;
        }

        return [$enrolcount, $userids];
    }

    /**
     * Performs synchronisation of user profile images.
     *
     * @return int the count of synced photos.
     */
    protected function sync_profile_images(): int
    {
        $counter = 0;
        foreach ($this->userphotos as $userid => $url) {
            if ($url) {
                $result = helper::update_user_profile_image($userid, $url);
                if ($result === helper::PROFILE_IMAGE_UPDATE_SUCCESSFUL) {
                    $counter++;
                    // mtrace("Profile image successfully downloaded and created for user '$userid' from $url."); 
                } else {
                    // mtrace($result); 
                }
            }
        }
        return $counter;
    }
}