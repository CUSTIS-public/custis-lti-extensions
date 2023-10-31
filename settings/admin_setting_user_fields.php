<?php

use tool_modeussync\repository\users_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Выпадающий список с полями, которые можно использовать в качестве сквозного идентификатора (ExternalPersonId) для синхронизации пользователей:
 * стандартные поля из таблицы user (представлены как user::название_поля (например, user::idnumber));
 * кастомные поля из таблицы user_info_field (представлены как user_info_field::field_id (например user_info_field::1)).
 */
class admin_setting_user_fields extends admin_setting_configselect
{
    public function __construct()
    {
        parent::__construct(
            'tool_modeussync/user_sync_field',
            'The field where the user ExternalPersonId is stored',
            'This field will be used to synchronize the list of users and their grades with Modeus.',
            'user::idnumber',
            null
        );
    }

    public function load_choices()
    {
        if (is_array($this->choices)) {
            return true;
        }
        $this->choices = array();

        // кастомные поля
        $fields = users_repository::get_custom_user_fields();
        foreach ($fields as $field) {
            $this->choices[$field->id] = $field->name;
        }

        //стандартные поля из таблицы user
        foreach (['id', 'idnumber'] as $field) {
            $this->choices['user::' . $field] = 'user -> ' . $field;
        }

        return true;
    }
}
