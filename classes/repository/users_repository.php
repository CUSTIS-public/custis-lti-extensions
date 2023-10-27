<?php
namespace tool_ltiextensions\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Репозиторий для users
 */
class users_repository
{
    public function getUserExternalIdGetter()
    {
        // Идентификатор персоны, по которому мы ищем пользователей, может находиться либо в таблице user, либо в user_info_data.
        // Получаем внешний идентификатор персоны по userid.
        // Где именно находится идентификатор - определяется настройками плагина.
        $userIdConfiguration = get_config('tool_ltiextensions', 'lti_field_user_id');
        if (!$userIdConfiguration) {
            throw new \Exception("Не задана настройка lti_field_user_id");
        }

        // $idField содержит либо название колонки таблицы user, либо идентификатор user_info_field для фильтрации записей в user_info_data.
        [$tableName, $idField] = explode('::', $userIdConfiguration, 2);

        if ($tableName == "user") {
            mtrace("Getting user ids by [user]->[$idField]");
            return function ($userid) use ($idField) {
                global $CFG, $DB;
                $user = $DB->get_record('user', ['id' => $userid]);

                if (!$user) {
                    return false;
                } else {
                    return $user->$idField;
                }
            };
        } else {
            mtrace("Getting user ids by [user_info_field]->[$idField]");
            return function ($userId) use ($idField) {
                global $CFG, $DB;
                $sql = "SELECT d.data FROM {user} u
                JOIN {user_info_data} d ON u.id = d.userid
                WHERE u.id = :userId AND d.fieldid = :idField";
                $user = $DB->get_record_sql($sql, ['userId' => $userId, 'idField' => $idField]);

                if (!$user) {
                    return false;
                } else {
                    return $user->id;
                }
            };
        }
    }

    public function getUserIdGetter()
    {
        // Идентификатор персоны, по которому мы ищем пользователей, может находиться либо в таблице user, либо в user_info_data.
        // Получаем внутренний идентификатор userid по внешнему идентификатору персоны.
        // Где именно находится идентификатор - определяется настройками плагина.
        $userIdConfiguration = get_config('tool_ltiextensions', 'lti_field_user_id');
        if (!$userIdConfiguration) {
            throw new \Exception("Не задана настройка lti_field_user_id");
        }

        // $idField содержит либо название колонки таблицы user, либо идентификатор user_info_field для фильтрации записей в user_info_data.
        [$tableName, $idField] = explode('::', $userIdConfiguration, 2);

        if ($tableName == "user") {
            mtrace("Syncing users by [user]->[$idField]");
            return function ($externalId) use ($idField) {
                global $CFG, $DB;
                $user = $DB->get_record('user', [$idField => $externalId]);

                if (!$user) {
                    return false;
                } else {
                    return $user->id;
                }
            };
        } else {
            mtrace("Syncing users by [user_info_field]->[$idField]");
            return function ($externalId) use ($idField) {
                global $CFG, $DB;
                $sql = "SELECT u.id FROM {user} u
                JOIN {user_info_data} d ON u.id = d.userid
                WHERE d.fieldid = :idField AND d.data = :externalId";
                $user = $DB->get_record_sql($sql, ['idField' => $idField, 'externalId' => $externalId]);

                if (!$user) {
                    return false;
                } else {
                    return $user->id;
                }
            };
        }
    }

    // Получает список полей пользователей из таблицы user_info_field.
    // Используется в настройках плагина.
    public static function get_custom_user_fields(): array
    {
        global $DB;

        $sql = "SELECT CONCAT('user_info_field::', id) as id, CONCAT('user_info_field -> ', shortname, ' (', name, ')') as name from mdl_user_info_field;";

        return $DB->get_records_sql($sql);
    }
}
