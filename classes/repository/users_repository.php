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
 * Users repository
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ltiextensions\repository;

defined('MOODLE_INTERNAL') || die();

class users_repository
{
    /**
     * Получает пользователей, которые еще не привязаны к LMS.
     * Для привязки используется стандартное поле из users
     *
     * @return array Пользователи (id, lti_id)
     */
    public static function get_not_bound_users(string $issuer, string $field): array
    {
        global $DB;

        $not_bound_users = users_repository::get_not_bound_users_query($issuer);

        $sql = "SELECT id, $field as lti_id from {user} 
            where id not in ($not_bound_users) 
            and $field is not null and $field <> ''";

        return $DB->get_records_sql($sql);
    }

    /**
     * Получает пользователей, которые еще не привязаны к LMS.
     * Для привязки используется кастомное поле из mdl_user_info_data
     *
     * @return array Пользователи (id, lti_id)
     */
    public static function get_not_bound_users_by_user_info_field(string $issuer, string $field): array
    {
        global $DB;

        $not_bound_users = users_repository::get_not_bound_users_query($issuer);

        $sql = "SELECT u.id, d.data as lti_id FROM mdl_user u join mdl_user_info_data d on u.id = d.userid 
        WHERE u.id not in ($not_bound_users) AND d.fieldid = $field AND d.data is not null and d.data <> ''";

        return $DB->get_records_sql($sql);
    }

    private static function get_not_bound_users_query(string $issuer): string
    {
        $issuer256 = hash('sha256', $issuer);

        return "SELECT userid FROM {auth_lti_linked_login} where issuer256 = '$issuer256'";
    }

    /**
     * Получает список полей пользователей из таблицы user_info_field
     *
     * @return array Список полей (id, name)
     */
    public static function get_custom_user_fields(): array
    {
        global $DB;

        $sql = "SELECT CONCAT('user_info_field::', id) as id, CONCAT('user_info_field -> ', shortname, ' (', name, ')') as name from mdl_user_info_field;";

        return $DB->get_records_sql($sql);
    }
}
