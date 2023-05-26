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
 * Lang strings.
 *
 * This files lists lang strings related to tool_ltiextensions.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Moodle LTI Extensions';
$string['auto_publish_as_lti_tools'] = 'Publish new courses and modules as LTI tools';
$string['manage'] = 'Moodle LTI Extensions';
$string['start_lti_sync'] = 'Start sync of membership and grades with LTI platforms';
$string['push_courses'] = 'Send info to LTI platforms about courses and their modules';
$string['pull_courses'] = 'Create new courses using data from LTI platforms';
$string['sync_user'] = 'The user who starts the LTI synchronization';
$string['sync_user_descr'] = 'This user will be added to the course as a teacher to start syncing via LTI. After working out the sync_members job, the user will be removed from the course';
$string['auto_link_users'] = 'Register Moodle users in LTI';
$string['provisioningmode'] = 'Provisioning mode. WARNING! Value changes don\'t affect existing LTI Tools, only new ones';
$string['platform_settings'] = 'Additional settings for convenient integration via LTI';
$string['platform_settings_descr'] = 'JSON format (it is important to use double quotes): { "deploymentid": { "lmsapi": "url" }}';
$string['common'] = 'Common settings of LTI extensions';
$string['lang'] = 'Language to publish LTI tool';
$string['lang_descr'] = 'Use only languages installed in Moodle. WARNING! Value changes don\'t affect existing LTI Tools, only new ones';
$string['lti_field_user_id'] = 'The field where the user ID for LTI is stored';
$string['lti_field_user_id_descr'] = 'This field will be used to synchronize the list of users and their grades with the LTI Platform. Attention! Changing this field will only bind new users correctly. Old users will remain tied to the old ID';
$string['default_category'] = 'Default category for created courses';
$string['default_category_descr'] = 'Courses created based on data from the Platform will be placed in this category';
