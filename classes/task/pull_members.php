<?php

namespace tool_modeussync\task;

use tool_modeussync\repository\users_repository;
use tool_modeussync\task\base\base_sync_job;

class pull_members extends base_sync_job
{
    public function get_name()
    {
        return 'pull_members';
    }

    public function work_precheck($lastClosedSession): bool
    {
        global $DB;
        $pushCoursesJobName = push_courses::name;
        $error_message = "All courses must be synchronized before members sync. Run job {$pushCoursesJobName} and try again. Skipping member sync this time";
        $push_coursesSessionType = $this->syncSessionTypeByTaskName[$pushCoursesJobName];
        $lastClosedPushCoursesSession = $this->lmsAdapterService->getLastClosedSession($push_coursesSessionType);
        if ($lastClosedPushCoursesSession === null) {
            mtrace($error_message);
            return false;
        }
        $lastPushCoursesEpoch = $this->epochFromSession($lastClosedPushCoursesSession);

        $selectSql = "SELECT c.id, c.timecreated, c.fullname
        FROM {course} c
        WHERE c.timecreated > :last_push_courses_date
        ";
        $queryParams = ['last_push_courses_date' => $lastPushCoursesEpoch];
        $notPushedCourses = $DB->get_records_sql($selectSql, $queryParams);
        if (count($notPushedCourses) > 0) {
            mtrace("Found new courses:");
            foreach ($notPushedCourses as $newCourse) {
                mtrace("- fullname '{$newCourse->fullname}', id {$newCourse->id}, timecreated {$newCourse->timecreated}");
            }
            mtrace($error_message);
            return false;
        }

        return true;
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
        $totalEnrolled = 0;
        foreach ($courseMembersList as $courseMembers) {
            $courseId = $courseMembers['courseId'];
            mtrace("Enrolling in course {$courseId}");
            $enrol = $DB->get_record('enrol', ['courseid' => $courseId, 'enrol' => 'manual']);
            if (!$enrol) {
                mtrace("Enrol for $courseId was not found. Skipping");
                continue;
            }

            foreach ($courseMembers['studentExternalPersonIds'] as $studentId) {
                $userId = $userGetter($studentId);
                if (!$userId) {
                    mtrace("Student $studentId wasn't found");
                    continue;
                }
                $enrolplugin->enrol_user($enrol, $userId, $studentRoleId);
                $totalEnrolled += 1;
            }

            foreach ($courseMembers['teacherExternalPersonIds'] as $teacherId) {
                $userId = $userGetter($teacherId);
                if (!$userId) {
                    mtrace("Teacher $teacherId wasn't found");
                    continue;
                }
                $enrolplugin->enrol_user($enrol, $userId, $teacherRoleId);
                $totalEnrolled += 1;
            }

            mtrace("");
        }
        mtrace("Total enrolled $totalEnrolled users");
    }
}
