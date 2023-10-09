<?php

use tool_ltiextensions\repository\users_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Выпадающий список с полями с информацией о пользователе:
 * стандартные поля из таблицы user (представлены как user::название_поля (например, user::idnumber));
 * кастомные поля из таблицы user_info_field (представлены как user_info_field::field_id (например user_info_field::1)).
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_user_fields extends admin_setting_configselect
{
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct()
    {
        parent::__construct(
            'tool_ltiextensions/lti_field_user_id',
            new lang_string('lti_field_user_id', 'tool_ltiextensions'),
            new lang_string('lti_field_user_id_descr', 'tool_ltiextensions'),
            'user::idnumber',
            null
        );
    }

    /**
     * Loads an array of choices for the configselect control
     *
     * @return bool always return true
     */
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
