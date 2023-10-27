<?php

namespace tool_ltiextensions\task;

use tool_ltiextensions\repository\users_repository;
use tool_ltiextensions\task\base\base_sync_job;

class push_grades extends base_sync_job
{
    public function get_name()
    {
        return 'push_grades';
    }

    public function do_work(array $currentSession, ?array $lastClosedSession)
    {
        global $CFG, $DB;

        $pushCoursesJobName = push_courses::name;
        $push_coursesSessionType = $this->syncSessionTypeByTaskName[$pushCoursesJobName];
        $lastClosedPushCoursesSession = $this->lmsAdapterService->getLastClosedSession($push_coursesSessionType);
        if ($lastClosedPushCoursesSession === null) {
            throw $this->getSyncCoursesException();
        }
        $lastPushCoursesEpoch = $this->epochFromSession($lastClosedPushCoursesSession);

        $selectSql = "SELECT gi.id, gi.courseid, gi.timecreated
        FROM {grade_items} gi
        WHERE gi.timecreated > :last_push_courses_date
          AND gi.itemtype = 'mod';
        ";
        $queryParams = ['last_push_courses_date' => $lastPushCoursesEpoch];
        $changedGradeItems = $DB->get_records_sql($selectSql, $queryParams);
        if (count($changedGradeItems) > 0) {
            mtrace("Found new grade items. This implies that new modules or courses may have been created. Changed:");
            foreach ($changedGradeItems as $changedGradeItem) {
                mtrace("- itemid {$changedGradeItem->id} courseid {$changedGradeItem->courseid}, timecreated {$changedGradeItem->timecreated}");
            }
            throw $this->getSyncCoursesException();
        }

        $selectSql = "SELECT gg.id,
        mc.id mcid,
        mc.course,
        gi.grademax,
        gg.finalgrade,
        gg.userid,
        gg.usermodified,
        gg.overridden,
        gg.timemodified
 FROM {grade_grades} gg
          INNER JOIN {grade_items} gi ON gi.id = gg.itemid
          INNER JOIN {modules} m ON m.name = gi.itemmodule
          INNER JOIN {course_modules} mc ON mc.course = gi.courseid AND mc.module = m.id AND mc.instance = gi.iteminstance
     AND ((gg.overridden > :last_sync_date1 AND gg.overridden != 0) OR (gg.timemodified IS NOT NULL AND gg.timemodified > :last_sync_date2))
     AND gi.itemtype = 'mod'
 ORDER BY mc.course, mc.id;
        ";

        $secondsInWeek = 604800;
        $maximumAge = $secondsInWeek * 2;
        $lastSyncEpoch = $this->epochFromSession($lastClosedSession);
        if ($lastSyncEpoch === null || (time() - $lastSyncEpoch > $maximumAge)) {
            $lastSyncEpoch = time() - $maximumAge;
        }
        $queryParams = ['last_sync_date1' => $lastSyncEpoch, 'last_sync_date2' => $lastSyncEpoch];

        mtrace("Searching new grades...");
        $grades = $DB->get_records_sql($selectSql, $queryParams);
        $request = $this->buildRequestFromGrades($grades);

        if (count($request->CourseScoresList) != 0) {
            mtrace("Found grades for courses:");
            foreach ($request->CourseScoresList as $courseScores) {
                mtrace("Course id {$courseScores['CourseId']}");
            }
            $this->lmsAdapterService->pushGrades($currentSession['id'], $request);
        } else {
            mtrace("New grades were not found.");
        }

    }

    private function getSyncCoursesException(): \Exception
    {
        $pushCoursesJobName = push_courses::name;
        return new \Exception("All courses and modules must be synchronized before grade sync. Run job {$pushCoursesJobName} and try again. Skipping grade sync this time");
    }

    private function buildRequestFromGrades(array $grades): \stdClass
    {
        $courseScoresList = array();
        $courseScores = null;
        $moduleScores = null;
        $users_repository = new users_repository();
        $userIdGetter = $users_repository->getUserExternalIdGetter();
        foreach ($grades as $grade) {
            if ($courseScores === null || $courseScores['CourseId'] !== $grade->course) {
                unset($courseScores);
                $courseScores = array();
                $courseScores['CourseId'] = $grade->course;
                $courseScores['ModuleScoreList'] = array();
                $courseScoresList[] = &$courseScores;
            }

            if ($moduleScores === null || $moduleScores['ModuleId'] !== $grade->mcid) {
                unset($moduleScores);
                $moduleScores = array();
                $moduleScores['ModuleId'] = $grade->mcid;
                $moduleScores['Scores'] = array();
                $courseScores['ModuleScoreList'][] = &$moduleScores;
            }

            $scoreModel = array();
            $scoreModel['Value'] = $grade->finalgrade;
            $scoreModel['MaxValue'] = $grade->grademax;
            $scoreModel['ExternalCreatedAt'] = $this->epochToDateString($grade->overridden);
            $scoreModel['ExternalTeacherPersonId'] = $userIdGetter($grade->usermodified);
            $scoreModel['ExternalStudentPersonId'] = $userIdGetter($grade->userid);

            $moduleScores['Scores'][] = $scoreModel;
        }

        $request = new \stdClass;
        $request->CourseScoresList = $courseScoresList;

        return $request;
    }

}