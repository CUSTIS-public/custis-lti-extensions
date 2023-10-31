<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    //Общие настройки
    $common_settings = new admin_settingpage('common', 'Common settings');
    //Настройки линковки пользователей (выбор идентификатора пользователя, который будет использоваться при синхронизации с Modeus)
    $auto_link_users_settings = new admin_settingpage('users_sync', 'Users sync settings');
    //Настройки создания новых курсов
    $pull_courses_settings = new admin_settingpage('courses_sync', 'Courses sync settings');

    if ($ADMIN->fulltree) {
        $setting = new admin_setting_configtextarea(
            'tool_modeussync/connection_settings',
            'Connection config',
            'JSON format (it is important to use double quotes): { "deploymentid": { "lmsapi": "url" }}',
            "",
            PARAM_RAW
        );
        $common_settings->add($setting);

        require_once __DIR__ . '/settings/admin_setting_user_fields.php';
        $auto_link_users_settings->add(new admin_setting_user_fields());

        $pull_courses_settings->add(new admin_settings_coursecat_select(
            'tool_modeussync/default_category',
            'Default category for created courses',
            'Courses created based on data from Modeus will be placed in this category',
            1
        ));
    }

    $ADMIN->add('tools', new admin_category('managemodeussync', 'Modeus Sync Plugin'));
    $ADMIN->add('managemodeussync', $common_settings);
    $ADMIN->add('managemodeussync', $auto_link_users_settings);
    $ADMIN->add('managemodeussync', $pull_courses_settings);
}
