<?php
namespace tool_ltiextensions\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Репозиторий для users
 */
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

    private static function get_not_bound_users_query(string $issuer): string
    {
        $issuer256 = hash('sha256', $issuer);

        return "SELECT userid FROM {auth_lti_linked_login} where issuer256 = '$issuer256'";
    }
}