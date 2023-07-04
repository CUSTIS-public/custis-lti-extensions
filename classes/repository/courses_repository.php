<?php
namespace tool_ltiextensions\repository;

use tool_ltiextensions\service\course_hash_service;
use tool_ltiextensions\courses_consts;
use context_course;
use context_module;

defined('MOODLE_INTERNAL') || die();

/**
 * Репозиторий для course
 */
class courses_repository
{
    private course_hash_service $course_hash_service;

    public function __construct()
    {
        $this->course_hash_service = new course_hash_service();
    }

    /**
     * Возвращает курсы, которые еще не были опубликованы через LTI
     *
     * @return array
     */
    public static function get_not_published_courses()
    {
        global $DB;

        $sql = "SELECT mc.*, c.id as contextid FROM {course} mc 
        join {context} c
        on c.instanceid = mc.id
        WHERE c.contextlevel in (" . CONTEXT_COURSE . ")
        and c.id not in (SELECT t.contextid FROM {enrol_lti_tools} t WHERE t.ltiversion = 'LTI-1p3')";

        return $DB->get_records_sql($sql);
    }

    /**
     * Возвращает модули, которые еще не были опубликованы через LTI
     *
     * @return array
     */
    public static function get_not_published_modules()
    {
        global $DB;

        $sql = "SELECT mmc.*, c.id as contextid FROM {course_modules} mmc 
        join {context} c
        on c.instanceid = mmc.id
        WHERE c.contextlevel in (" . CONTEXT_MODULE . ")
        and c.id not in (SELECT t.contextid FROM {enrol_lti_tools} t WHERE t.ltiversion = 'LTI-1p3')";

        return $DB->get_records_sql($sql);
    }

    /**
     * Получает курсы по их idnumber
     *
     * @return array курсы
     */
    public static function get_courses_by_idnumbers(array $idnumbers)
    {
        if (count($idnumbers) == 0) {
            return array();
        }

        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($idnumbers);
        $sql = "SELECT id, idnumber FROM {course} mc WHERE idnumber $insql";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Получает курсы по их shortname
     *
     * @return array курсы
     */
    public static function get_courses_by_shortnames(array $shortnames)
    {
        if (count($shortnames) == 0) {
            return array();
        }

        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($shortnames);
        $sql = "SELECT id, shortname FROM {course} mc WHERE shortname $insql";

        return $DB->get_records_sql($sql, $params);
    }

    public function get_course_modules($course)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $modinfosections = $modinfo->get_sections();
        $modules = array();

        mtrace("Getting course modules [$course->fullname] ($course->id)");

        foreach ($sections as $key => $section) {
            if (!array_key_exists($section->section, $modinfosections)) {
                continue;
            }
            foreach ($modinfosections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];

                $module = array();

                $modcontext = context_module::instance($cm->id);

                $lti = $DB->get_records('enrol_lti_tools', array('contextid' => $modcontext->id), 'uuid', 'uuid', 0, 1);
                reset($lti);
                $firstlti = current($lti);

                if (count($lti) == 0) {
                    continue;
                }

                $module['id'] = $cm->id;
                $module['lmsIdNumber'] = $cm->idnumber;
                $module['name'] = external_format_string($cm->name, $modcontext->id);
                $module['customLtiProperties'] = "id=$firstlti->uuid";
                $module['moduleTypeId'] = $cm->modname;

                $modules[] = $module;
            }
        }

        return $modules;
    }

    public function get_unpublished_courses($customfieldid)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        //retrieve not published courses
        $sql = "SELECT c.* FROM {course} c LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id AND cfd.fieldid = ? WHERE cfd.id IS NULL";
        $courses = $DB->get_records_sql($sql, [$customfieldid]);

        //create return value
        $coursesinfo = array();
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $context = context_course::instance($course->id, IGNORE_MISSING);
            $lti = $DB->get_records('enrol_lti_tools', array('contextid' => $context->id), 'uuid', 'uuid', 0, 1);

            if (count($lti) == 0) {
                continue;
            }

            reset($lti);
            $firstlti = current($lti);

            $courseinfo = array();
            $courseinfo['id'] = $course->id;
            $courseinfo['lmsIdNumber'] = $course->idnumber;
            $courseinfo['name'] = external_format_string($course->fullname, $context->id);
            $courseinfo['customLtiProperties'] = "id=$firstlti->uuid";
            $courseinfo['modules'] = $this->get_course_modules($course);

            $coursesinfo[] = $courseinfo;
        }

        return $coursesinfo;
    }

    public function get_modified_courses($customfieldid)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        //retrieve published courses
        $sql = "SELECT c.* FROM {course} c JOIN {customfield_data} cfd ON cfd.instanceid = c.id AND cfd.fieldid = ?";
        $publishedCourses = $DB->get_records_sql($sql, [$customfieldid]);

        //create return value
        $coursesinfo = array();
        if (!empty($publishedCourses)) {
            $sql = "SELECT c.id, cfd.value FROM {course} c JOIN {customfield_data} cfd ON cfd.instanceid = c.id AND cfd.fieldid = ?";
            $publishedCoursesStates = $DB->get_records_sql_menu($sql, [$customfieldid]);

            //processing published courses to check their modifications
            foreach ($publishedCourses as $course) {
                if ($course->id == SITEID) {
                    continue;
                }

                $savedHash = $publishedCoursesStates[$course->id];
                if ($savedHash == $this->course_hash_service->get_course_state_hash($course)) {
                    continue;
                }

                $context = context_course::instance($course->id, IGNORE_MISSING);
                $lti = $DB->get_records('enrol_lti_tools', array('contextid' => $context->id), 'uuid', 'uuid', 0, 1);

                if (count($lti) == 0) {
                    continue;
                }

                reset($lti);
                $firstlti = current($lti);

                $courseinfo = array();
                $courseinfo['id'] = $course->id;
                $courseinfo['lmsIdNumber'] = $course->idnumber;
                $courseinfo['name'] = external_format_string($course->fullname, $context->id);
                $courseinfo['customLtiProperties'] = "id=$firstlti->uuid";
                $courseinfo['modules'] = $this->get_course_modules($course);

                $coursesinfo[] = $courseinfo;
            }
        }

        return $coursesinfo;
    }

    public function get_module_types()
    {
        $lang = force_current_language('ru');
        try {
            global $CFG, $DB;
            require_once($CFG->dirroot . "/course/lib.php");

            $types = $DB->get_records('modules', array('visible' => true));

            //create return value
            $typesinfo = array();
            foreach ($types as $moduleType) {
                $moduleTypeinfo = array();
                $moduleTypeinfo['id'] = $moduleType->name;
                $moduleTypeinfo['name'] = $this->get_module_label($moduleType->name);
                $moduleTypeinfo['canCreate'] = !in_array($moduleType->name, courses_consts::$unsupported_module_types);

                $typesinfo[] = $moduleTypeinfo;
            }

            return $typesinfo;
        } finally {
            force_current_language($lang);
        }
    }

    private static function get_module_label(string $modulename): string
    {
        if (get_string_manager()->string_exists('modulename', $modulename)) {
            $modulename = get_string('modulename', $modulename);
        }

        return $modulename;
    }
}