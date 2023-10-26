<?php

namespace tool_ltiextensions\task;

use tool_ltiextensions\repository\users_repository;
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
        $users_repository = new users_repository();
        $userGetter = $users_repository->getUserIdGetter();

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
}
