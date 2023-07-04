<?php
namespace tool_ltiextensions\service;
use context_module;

defined('MOODLE_INTERNAL') || die();

/**
 * Сервис, вычисляющий hash для курса (с учетом его структуры)
 */
class course_hash_service
{
    public function get_course_state_hash($course)
    {
        global $CFG;
        require_once($CFG->dirroot . "/course/lib.php");

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $modinfosections = $modinfo->get_sections();

        $toHash = "{$course->id}-{$course->fullname}";

        foreach ($sections as $key => $section) {
            if (!array_key_exists($section->section, $modinfosections)) {
                continue;
            }
            $toHash .= "|{$section->id}-{$section->sequence}-{$section->name}-[";
            foreach ($modinfosections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $modcontext = context_module::instance($cm->id);

                $toHash .= external_format_string($cm->name, $modcontext->id) . ",";
            }
            $toHash .= "]";
        }

        return md5($toHash);
    }
}