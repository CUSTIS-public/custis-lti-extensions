<?php

namespace tool_ltiextensions\task;

use tool_ltiextensions\task\base\base_sync_job;

class pull_members extends base_sync_job
{
    public function get_name()
    {
        return 'pull_members';
    }

    public function do_work(array $currentSession, ?array $lastClosedSession)
    {
        global $CFG, $DB;
        require_once $CFG->libdir . '/accesslib.php';

        $courseMembersList = $this->lmsAdapterService->getCourseMembers($currentSession['id']);
        $enrolplugin = enrol_get_plugin('manual');
        $studentRoleId = $DB->get_record('role', ['shortname' => 'student'])->id;
        $teacherRoleId = $DB->get_record('role', ['shortname' => 'editingteacher'])->id;
        $userGetter = $this->getUserGetter();

        mtrace("Begin to enrol...");
        foreach ($courseMembersList as $courseMembers) {
            $courseId = $courseMembers['courseId'];
            mtrace("Enrolling in course {$courseId}");
            $enrol = $DB->get_record('enrol', ['courseid' => $courseId, 'enrol' => 'manual']);

            foreach ($courseMembers['studentExternalPersonIds'] as $studentId) {
                $userId = $userGetter($studentId);
                if (!$userId) {
                    mtrace("Student $studentId wasn't found");
                    continue;
                }
                $enrolplugin->enrol_user($enrol, $userId, $studentRoleId);
            }

            foreach ($courseMembers['teacherExternalPersonIds'] as $teacherId) {
                $userId = $userGetter($teacherId);
                if (!$userId) {
                    mtrace("Teacher $teacherId wasn't found");
                    continue;
                }
                $enrolplugin->enrol_user($enrol, $userId, $teacherRoleId);
            }

            mtrace("");
        }
    }

    private function getUserGetter()
    {
        // Идентификатор, по которому мы ищем пользователей, может находиться либо в таблице user, либо в user_info_data.
        // Где именно находится идентификатор - определяется настройками плагина.
        $userIdConfiguration = get_config('tool_ltiextensions', 'lti_field_user_id');
        if (!$userIdConfiguration) {
            throw new \Exception("Не задана настройка lti_field_user_id");
        }

        // $idField содержит либо название колонки таблицы user, либо идентификатор user_info_field для фильтрации записей в user_info_data.
        [$tableName, $idField] = explode('::', $userIdConfiguration, 2);

        if ($tableName == "user") {
            mtrace("Syncing users by [user]->[$idField]");
            return function ($externalId) use ($idField) {
                global $CFG, $DB;
                $user = $DB->get_record('user', [$idField => $externalId]);

                if (!$user) {
                    return false;
                } else {
                    return $user->id;
                }
            };
        } else {
            mtrace("Syncing users by [user_info_field]->[$idField]");
            return function ($externalId) use ($idField) {
                global $CFG, $DB;
                $sql = "SELECT u.id FROM {user} u
                JOIN {user_info_data} d ON u.id = d.userid
                WHERE d.fieldid = :idField AND d.data = :externalId";
                $user = $DB->get_record_sql($sql, ['idField' => $idField, 'externalId' => $externalId]);

                if (!$user) {
                    return false;
                } else {
                    return $user->id;
                }
            };
        }
    }
}
