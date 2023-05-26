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

use core\task\scheduled_task;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use Throwable;
use tool_ltiextensions\debug_utils;
use tool_ltiextensions\repository\users_repository;

/**
 * Привязывает пользователей Moodle к пользователям LTI.
 * Для работы джоба необходимо указать, какое из полей пользователея будет использоваться в качестве ИД в LTI.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_link_users extends scheduled_task
{
    protected $auth;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('auto_link_users', 'tool_ltiextensions');
    }

    protected function process_users(string $iss, array $users): int
    {
        $processed = 0;
        foreach ($users as $user) {
            try {
                $this->auth->create_user_binding($iss, $user->lti_id, $user->id);
                $processed++;
            } catch (Throwable $e) {
                mtrace("Error while working with user '$user->id'");
                debug_utils::traceError($e);
            }
        }

        return $processed;
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

        $this->auth = get_auth_plugin('lti');

        $appregistrationrepo = new application_registration_repository();
        $appregistrations = $appregistrationrepo->find_all();

        foreach ($appregistrations as $appregistration) {
            $iss = $appregistration->get_platformid()->out(false);
            $platformName = "'" . $appregistration->get_name() . "' ('" . $appregistration->get_id() . "', $iss)";
            try {
                mtrace("");
                mtrace("Starting linking users with platform $platformName");

                // либо user::field_name, либо user_info_field::field_id
                $userIdField = get_config('tool_ltiextensions', 'lti_field_user_id');
                if (!$userIdField) {
                    throw "Не задана настройка lti_field_user_id";
                }

                [$tableName, $field] = explode('::', $userIdField, 2);

                if ($tableName == "user") {
                    mtrace("Link users by [user]->[" . $field . "]");
                    $users = users_repository::get_not_bound_users($iss, $field);
                } else { //$tableName == "user_info_field"
                    mtrace("Link users by [user_info_field]->[" . $field . "]");
                    $users = users_repository::get_not_bound_users_by_user_info_field($iss, $field);
                }
                mtrace("Received " . count($users) . " not bound users");

                $processed = $this->process_users($iss, $users);

                mtrace("Processed $processed users from " . count($users));
            } catch (Throwable $e) {
                mtrace("Error while working with platform $platformName");
                debug_utils::traceError($e);
            }
        }
    }
}
