<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    //Общие настройки
    $common_settings = new admin_settingpage('common', 'Common settings');
    //Настройки линковки пользователей (выбор идентификатора пользователя, который будет использоваться при синхронизации с Modeus)
    $auto_link_users_settings = new admin_settingpage('users_sync', 'Users sync settings');
    //Настройки создания новых курсов
    $courses_settings = new admin_settingpage('courses_sync', 'Courses sync settings');

    if ($ADMIN->fulltree) {
        $connection_config = new admin_setting_configtextarea(
            'tool_modeussync/connection_settings',
            'Connection config',
            'JSON format (it is important to use double quotes): { "deploymentid": { "lmsapi": "url" }}',
            "",
            PARAM_RAW
        );
        $common_settings->add($connection_config);

        require_once __DIR__ . '/settings/admin_setting_user_fields.php';
        $auto_link_users_settings->add(new admin_setting_user_fields());

        $courses_settings->add(new admin_settings_coursecat_select(
            'tool_modeussync/default_category',
            'Default category for created courses',
            'Courses created based on data from Modeus will be placed in this category',
            1
        ));
        $courses_settings->add(new admin_setting_configtext(
            'tool_modeussync/sync_courses_max_age',
            'Max age for courses to be pushed to Modeus',
            'Number in seconds',
            "31536000",
            PARAM_INT,
            "20"
        ));
        $courses_settings->add(new admin_setting_configtext(
            'tool_modeussync/sync_grades_max_age',
            'Max age for grades to be pushed to Modeus',
            'Number in seconds',
            "2419200",
            PARAM_INT,
            "20"
        ));
    }

    $ADMIN->add('tools', new admin_category('managemodeussync', 'Modeus Sync Plugin'));
    $ADMIN->add('managemodeussync', $common_settings);
    $ADMIN->add('managemodeussync', $auto_link_users_settings);
    $ADMIN->add('managemodeussync', $courses_settings);
}
