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
 * Lang strings.
 *
 * This files lists lang strings related to tool_ltiextensions.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Custis LTI Extensions';
$string['auto_publish_as_lti_tools'] = 'Публикация новых курсов и модулей в качестве инструментов LTI';
$string['manage'] = 'Custis LTI Extensions';
$string['start_lti_sync'] = 'Старт синхронизации участников и оценок с Платформами LTI';
$string['push_courses'] = 'Отправка информации о курсах и их модулях в Платформы LTI';
$string['pull_courses'] = 'Создание новых курсов по данным от Платформ LTI';
$string['sync_user'] = 'Пользователь, запускающий синхронизацию LTI';
$string['sync_user_descr'] = 'Этот пользователь будет добавлен в курс как преподаватель, чтобы начать синхронизацию через LTI. После отработки джоба sync_members пользователь будет удален из курса';
$string['auto_link_users'] = 'Регистрация пользователей Moodle в LTI';
$string['provisioningmode'] = 'Режим подготовки. Внимание! Изменение значения не влияет на существующие инструменты LTI, только на новые';
$string['platform_settings'] = 'Дополнительные настройки для удобной интеграции через LTI';
$string['platform_settings_descr'] = 'Формат JSON (важно использовать двойные кавычки): { "deploymentid": { "lmsapi": "url" }}';
$string['common'] = 'Общие настройки плагина Custis LTI Extensions';
$string['lang'] = 'Язык для публикации Инструмента LTI';
$string['lang_descr'] = 'Используйте только языки, установленные в Moodle. Внимание! Изменение значения не влияет на существующие Инструменты LTI, только на новые';
$string['lti_field_user_id'] = 'Поле, в котором хранится ИД пользователя для LTI';
$string['lti_field_user_id_descr'] = 'Это поле будет использоваться для синхронизации списка пользователей и их оценок с Платформами LTI. ВНИМАНИЕ! Изменение этого поля приведет к корректной привязке только новых пользователей. Старые пользователи так и останутся привязанными со старым ИД';
$string['default_category'] = 'Категория по умолчанию для создаваемых курсов';
$string['default_category_descr'] = 'Курсы, создаваемые по данным с Платформ LTI, будут помещены в эту категорию';
