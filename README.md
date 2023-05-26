# moodle-lti-extensions

Moodle extensions that make LTI integration a bit easier. This extensions publishes all created courses and elements as LTI tools, registers existing users into LTI, sends information about courses to the Platform, etc.

# Jobs

| Job | Description | Settings | Platform's API
| --- | --- | --- | --- |
| `auto_link_users` | Registers users in LTI: assigns Moodle user IDs by which they will be matched with platform users (Modeus)<br/>Jeb takes the ID for matching from the field specified in the settings and indicates that this ID will be used when interacting via LTI.<br/>The specified field should not be changed: its change will not entail the registration of the user in LTI with a new ID. In order for this to happen, you will need to delete the entry in the auth_lt i_linked_login table |

# Develop

1. Run Moodle using docker (you can use [Bitnami container](https://hub.docker.com/r/bitnami/moodle))
1. Clone this repo into `admin\tool\ltiextensions` (.git folder shoud be in `admin\tool\ltiextensions\.git`)
1. Change code in `admin\tool\ltiextensions`. The changes would be automatically applied

# Publish

1. Zip this repository (or `admin\tool\ltiextensions` folder). Delete `.git` folder from zip-file
1. Tag the commit with `yyyyMMddvv`
1. Create release on github
1. Install plugin into Moodle using `Administration > Site administration > Plugins > Install plugins`

# License

This plugin is distributed under the terms of the General Public License (see http://www.gnu.org/licenses/gpl.txt for details). This software is provided "AS IS" without a warranty of any kind.

# Credits

This module was funded by CUSTIS and developed by Igor Shatalkin