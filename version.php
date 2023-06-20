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
 * Version info
 *
 * This file contains version information about tool_ltiextensions.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2023062007;     // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022112800;     // Requires this Moodle version.
$plugin->component = 'tool_ltiextensions'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = [
    'auth_lti' => 2022041900,
    'enrol_lti' => 2022041901
];