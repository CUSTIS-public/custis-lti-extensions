<?php

namespace tool_modeussync\task;

use tool_modeussync\courses_consts;
use tool_modeussync\task\base\base_sync_job;

class push_courses extends base_sync_job
{
    public const name = 'push_courses';

    public function get_name()
    {
        return push_courses::name;
    }

    public function do_work(array $currentSession, ?array $lastClosedSession)
    {
        $lastSyncTime = $this->epochFromSession($lastClosedSession);
        $secondsInYear = 31536000;
        $minimumCreatedAt = time() - 1 * $secondsInYear;

        $courses = $this->getCoursesToPush($minimumCreatedAt, $lastSyncTime);
        $moduleTypeInfos = $this->get_module_types();

        $request = new \stdClass;
        $request->courses = $courses;
        $request->moduleTypes = $moduleTypeInfos;
        $deletedCourseIds = $this->getDeletedCourseIds($minimumCreatedAt, $lastSyncTime);
        $request->deletedCourseIds = $deletedCourseIds;

        $this->lmsAdapterService->updateCoursesAndModuleTypes($currentSession['id'], $request);
    }

    private function getCoursesToPush($minimumCreatedAt, ?int $lastSyncTime): array
    {
        global $CFG, $DB;

        mtrace("Selecting courses for push...");
        $fromSql = "
        FROM {course} c
                 LEFT JOIN (SELECT DISTINCT(l.courseid) as courseid
                            FROM {logstore_standard_log} l
                            WHERE (eventname = '\core\\event\course_module_created' OR
                                   eventname = '\core\\event\course_module_updated' OR
                                   eventname = '\core\\event\course_module_deleted')
                              AND (:last_sync_date1::int is null OR :last_sync_date2 <= l.timecreated)) l
                           ON l.courseid = c.id
        WHERE c.timecreated >= :minimum_date
          AND (
            (:last_sync_date3::int is null OR :last_sync_date4 <= c.timecreated OR :last_sync_date5 <= c.timemodified) OR
                l.courseid is not null)";
        $countSql = "SELECT count(DISTINCT c.id) {$fromSql}";
        $queryParams = [
            'minimum_date' => $minimumCreatedAt,
            'last_sync_date1' => $lastSyncTime,
            'last_sync_date2' => $lastSyncTime,
            'last_sync_date3' => $lastSyncTime,
            'last_sync_date4' => $lastSyncTime,
            'last_sync_date5' => $lastSyncTime,
        ];
        $coursesCount = $DB->count_records_sql($countSql, $queryParams);
        $lastSyncTimeStr = $lastSyncTime === null ? '--' : $lastSyncTime;
        mtrace("Found {$coursesCount} new/changed courses (after last sync time: {$lastSyncTimeStr})");

        mtrace("Querying data...");
        $selectSql = "SELECT c.* {$fromSql}";
        $courses = $DB->get_records_sql($selectSql, $queryParams);

        $courseModels = [];
        foreach ($courses as $course) {
            $nextCourse = [];
            $nextCourse['id'] = $course->id;
            $nextCourse['lmsIdNumber'] = $course->idnumber;
            $nextCourse['name'] = $course->fullname;
            $nextCourse['modules'] = $this->getCourseModules($course);

            $courseModels[] = $nextCourse;
        }
        return $courseModels;
    }

    public function getCourseModules($course)
    {
        global $CFG, $DB;
        require_once $CFG->dirroot . "/course/lib.php";

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $modinfosections = $modinfo->get_sections();
        $modules = array();

        mtrace("Getting course modules [$course->fullname] ($course->id)");

        foreach ($sections as $key => $section) {
            if (!array_key_exists($section->section, $modinfosections)) {
                continue;
            }
            foreach ($modinfosections[$section->section] as $courseModuleId) {
                $courseModuleInfo = $modinfo->cms[$courseModuleId];

                $module = array();

                $module['id'] = $courseModuleInfo->id;
                $module['lmsIdNumber'] = $courseModuleInfo->idnumber;
                $module['name'] = $courseModuleInfo->name;
                $module['moduleTypeId'] = $courseModuleInfo->modname;

                $modules[] = $module;
            }
        }

        return $modules;
    }
    public function get_module_types()
    {
        $oldLanguage = force_current_language('ru');
        try {
            global $CFG, $DB;
            require_once $CFG->dirroot . "/course/lib.php";

            $types = $DB->get_records('modules', array('visible' => true));

            $moduleTypeInfos = array();
            foreach ($types as $moduleType) {
                $moduleTypeinfo = array();
                $moduleTypeinfo['id'] = $moduleType->name;
                $moduleTypeinfo['name'] = $this->get_module_label($moduleType->name);
                $moduleTypeinfo['canCreate'] = !in_array($moduleType->name, courses_consts::$unsupported_module_types);

                $moduleTypeInfos[] = $moduleTypeinfo;
            }

            return $moduleTypeInfos;
        } finally {
            force_current_language($oldLanguage);
        }
    }

    private static function get_module_label(string $modulename): string
    {
        if (get_string_manager()->string_exists('modulename', $modulename)) {
            $modulename = get_string('modulename', $modulename);
        }

        return $modulename;
    }

    private function getDeletedCourseIds($minimumCreatedAt, ?int $lastSyncTime): array
    {
        global $CFG, $DB;

        mtrace("Searching deleted courses...");
        $fromSql = "FROM {logstore_standard_log} l
                WHERE eventname = '\core\\event\course_deleted' AND :minimum_date <= l.timecreated AND (:last_sync_date1::int is null OR :last_sync_date2 <= l.timecreated)";
        $countSql = "SELECT count(DISTINCT l.courseid) {$fromSql}";
        $queryParams = [
            'minimum_date' => $minimumCreatedAt,
            'last_sync_date1' => $lastSyncTime,
            'last_sync_date2' => $lastSyncTime,
        ];
        $coursesCount = $DB->count_records_sql($countSql, $queryParams);
        $lastSyncTimeStr = $lastSyncTime === null ? '--' : $lastSyncTime;
        mtrace("Found {$coursesCount} deleted courses (after last sync time: {$lastSyncTimeStr})");

        mtrace("Querying deleted course ids...");
        $selectSql = "SELECT DISTINCT l.courseid as id {$fromSql}";
        $courses = $DB->get_records_sql($selectSql, $queryParams);

        return array_map(fn($c) => $c->id, array_values($courses));
    }
}
