# custis-lti-extensions

Moodle extensions that make LTI integration a bit easier. This extensions publishes all created courses and elements as LTI tools, registers existing users into LTI, sends information about courses to the Platform, etc.

# Architecture

Plugin consists of several jobs. Some of them request LTI Platform's APIs. These APIs should be implemented in Platform in addition to APIs, described in LTI standart. Base url of additional APIs is set in `platform_settings`. Additional Platform APIs are described in [open api specification](swagger.json). Tool is authorized in Platform according to [LTI security standart](https://www.imsglobal.org/spec/security/v1p0/#securing_web_services).

# Jobs

| Job | Description | Settings | Platform's API
| --- | --- | --- | --- |
| `auto_link_users` | Registers users in LTI: assigns special IDs to Moodle user by which they will be matched with Platform users <br/>Job takes the ID for matching from the field specified in the settings and indicates that this ID will be used when interacting via LTI.<br/>The specified field should not be changed: its change will not entail the registration of the user in LTI with a new ID. In order for this to happen, you will need to delete the entry in the `auth_lti_linked_login` table | `lti_field_user_id` - field with ID for LTI integration. Supports `idnumber` and `id` fields from the `user` table, as well as all custom fields (from `user_info_field` table)| -
| `pull_courses` | Creates courses with a structure (course, topics, elements). <br/> Does not support the creation of H5P, SCORM, IMS elements (these elements can only be added manually) | - | `get-courses-to-create`
| `auto_publish_as_lti_tools` | Publishes courses and their elements as LTI Tool.<br/>By default, Moodle courses and elements should be manually published as LTI tools. Job does this automatically. | See `auto_publish_as_lti_tools` section in Moodle settings. Changes in `auto_publish_as_lti_tools` settings will not entail the corresponding changes in already published courses and elements. | -
| `push_courses` | Transmits course data to LTI Platforms (courses and their elements\` names). | - | `save-courses`,`save-course-modules`
| `start_lti_sync` | Starts synchronization via LTI. <br/> In pure LTI, synchronization begins when at least one course participant has completed Launch action. This job emulates Launch from under the administrator. The data structure is the same as in Launch <br/> By itself, synchronization will occur in `sync_members` and `sync_grades`. In fact this job requests API addresses from Platform, which will be used to synchronize participants and their grades | `sync_user` - the user who starts the LTI synchronization | `getlinks`

# Develop

1. Run Moodle using docker (you can use [Bitnami container](https://hub.docker.com/r/bitnami/moodle))
1. Clone this repo into `admin\tool\ltiextensions` (.git folder shoud be in `admin\tool\ltiextensions\.git`)
1. Change code in `admin\tool\ltiextensions`. The changes would be automatically applied

# Publish

1. Change version in version.php
1. Zip this repository (or `admin\tool\ltiextensions` folder). Delete `.git` folder from zip-file
1. Tag the commit with `yyyyMMddvv`
1. Create release on github
1. Install plugin into Moodle using `Administration > Site administration > Plugins > Install plugins`

# License

This plugin is distributed under the terms of the General Public License (see http://www.gnu.org/licenses/gpl.txt for details). This software is provided "AS IS" without a warranty of any kind.

# Credits

This module was funded by CUSTIS and developed by Igor Shatalkin