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

/**
 * LtiExtensions settings.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('manageltiextensions', new lang_string('manage', 'tool_ltiextensions')));

    //===============================
    //Общие настройки
    $common_settings = new admin_settingpage('common', new lang_string('common', 'tool_ltiextensions'));

    if ($ADMIN->fulltree) {
        $setting = new admin_setting_configtextarea(
            'tool_ltiextensions/platform_settings',
            new lang_string('platform_settings', 'tool_ltiextensions'),
            new lang_string('platform_settings_descr', 'tool_ltiextensions'),
            "",
            PARAM_RAW
        );
        $common_settings->add($setting);
    }

    $ADMIN->add('manageltiextensions', $common_settings);

    //===============================
    //Настройки джоба auto_publish_as_lti_tools
    $auto_publish_settings = new admin_settingpage('auto_publish_as_lti_tools', new lang_string('auto_publish_as_lti_tools', 'tool_ltiextensions'));

    if ($ADMIN->fulltree) {
        global $CFG;
        require_once($CFG->dirroot . '/auth/lti/auth.php');

        $authmodes = [
            auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY => get_string('provisioningmodeauto', 'auth_lti'),
            auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING => get_string('provisioningmodenewexisting', 'auth_lti'),
            auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY => get_string('provisioningmodeexistingonly', 'auth_lti')
        ];

        $auto_publish_settings->add(new admin_setting_configselect(
            'tool_ltiextensions/provisioningmodeinstructor',
            get_string('provisioningmodeteacherlaunch', 'enrol_lti'),
            get_string('provisioningmode', 'tool_ltiextensions'),
            auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY,
            $authmodes
        ));

        $auto_publish_settings->add(new admin_setting_configselect(
            'tool_ltiextensions/provisioningmodelearner',
            get_string('provisioningmodestudentlaunch', 'enrol_lti'),
            get_string('provisioningmode', 'tool_ltiextensions'),
            auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY,
            $authmodes
        ));

        $auto_publish_settings->add(new admin_setting_requiredtext(
            'tool_ltiextensions/lang',
            new lang_string('lang', 'tool_ltiextensions'),
            new lang_string('lang_descr', 'tool_ltiextensions'),
            'en',
            PARAM_TEXT
        ));
    }

    $ADMIN->add('manageltiextensions', $auto_publish_settings);

    //===============================
    //Настройки джоба start_lti_sync
    $start_sync_settings = new admin_settingpage('start_lti_sync', new lang_string('start_lti_sync', 'tool_ltiextensions'));

    if ($ADMIN->fulltree) {
        require_once(__DIR__ . '/settings/admin_setting_admins.php');
        $setting = new admin_setting_admins(
            'tool_ltiextensions/sync_user',
            new lang_string('sync_user', 'tool_ltiextensions'),
            new lang_string('sync_user_descr', 'tool_ltiextensions')
        );
        $start_sync_settings->add($setting);
    }

    $ADMIN->add('manageltiextensions', $start_sync_settings);

    //===============================
    //Настройки джоба auto_link_users
    $auto_link_users_settings = new admin_settingpage('auto_link_users', new lang_string('auto_link_users', 'tool_ltiextensions'));

    if ($ADMIN->fulltree) {
        require_once(__DIR__ . '/settings/admin_setting_user_fields.php');
        $auto_link_users_settings->add(new admin_setting_user_fields());
    }

    $ADMIN->add('manageltiextensions', $auto_link_users_settings);

    //===============================
    //Настройки джоба pull_courses
    $pull_courses_settings = new admin_settingpage('pull_courses', new lang_string('pull_courses', 'tool_ltiextensions'));

    if ($ADMIN->fulltree) {

        $pull_courses_settings->add(new admin_settings_coursecat_select(
            'tool_ltiextensions/default_category',
            new lang_string('default_category', 'tool_ltiextensions'),
            new lang_string('default_category_descr', 'tool_ltiextensions'),
            1
        ));
    }

    $ADMIN->add('manageltiextensions', $pull_courses_settings);
}
