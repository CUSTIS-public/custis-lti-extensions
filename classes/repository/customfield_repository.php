<?php
namespace tool_ltiextensions\repository;

use tool_ltiextensions\service\course_hash_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Репозиторий для работы с кастомными записями custom field (расширениями для стандартной схемы).
 * Moodle предоставляет эти таблицы для возможности сохранения дополнительной информации для записей в других таблицах.
 */
class customfield_repository
{
    private course_hash_service $course_hash_service;

    public function __construct()
    {
        $this->course_hash_service = new course_hash_service();
    }

    /**
     * Возвращает id для custom field по заданному имени. Создает запись в БД, если ее еще нет с заданным customfielddescription.
     */
    public function get_or_create_custom_field_id(string $customfieldname, string $customfielddescription)
    {
        global $CFG, $DB;

        $customfieldid = 0;
        $customfieldexists = $DB->record_exists('customfield_field', array('name' => $customfieldname));
        if (!$customfieldexists) {
            $time = time();
            $customfield = new \stdClass();
            $customfield->shortname = $customfieldname;
            $customfield->name = $customfieldname;
            $customfield->description = $customfielddescription;
            $customfield->type = $customfieldname;
            $customfield->timecreated = $time;
            $customfield->timemodified = $time;

            $customfieldid = $DB->insert_record('customfield_field', $customfield);
        } else {
            $customfieldid = (int) $DB->get_field('customfield_field', 'id', array('name' => $customfieldname), MUST_EXIST);
        }

        return $customfieldid;
    }

    /**
     * Сохраняет или обновляет запись о времени синхронизации контекста LTI (=курса)
     */
    public function save_lticontext_membership_sync_time(string $customfieldid, string $lticontextid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/course/lib.php");

        $customfielddataexists = $DB->record_exists('customfield_data', array('fieldid' => $customfieldid, 'instanceid' => $lticontextid));

        $time = time();
        $fielddata = new \stdClass();
        $fielddata->fieldid = $customfieldid;
        $fielddata->instanceid = $lticontextid;
        $fielddata->value = $time;
        $fielddata->valueformat = 1;
        $fielddata->timecreated = $time;
        $fielddata->timemodified = $time;

        if ($customfielddataexists) {
            $customfielddataid = (int) $DB->get_field('customfield_data', 'id', array('fieldid' => $customfieldid, 'instanceid' => $lticontextid), MUST_EXIST);
            $fielddata->id = $customfielddataid;

            $DB->update_record('customfield_data', $fielddata);
        } else {
            $DB->insert_record('customfield_data', $fielddata);
        }
    }

    /**
     * Возвращает время, прошедшее с момента последней синхронизации контекста LTI (=курса).
     * В LMS Adapter отправляется именно относительное (прошедшее) время, чтобы избежать проблем с рассинхронизацией часов.
     */
    public function get_lticontext_membership_passed_sync_time(string $customfieldid, string $lticontextid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/course/lib.php");

        $lastsynctime = (int) $DB->get_field('customfield_data', 'value', array('fieldid' => $customfieldid, 'instanceid' => $lticontextid), IGNORE_MISSING);

        if ($lastsynctime) {
            $result = time() - $lastsynctime;
            return $result;
        }
    }

    /**
     * Обновляет статус публикации для указанных курсов, используя customfield 
     */
    public function update_course_publish_status($unpublishedCourses, $modifiedCourses, $customfieldid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/course/lib.php");

        if (!empty($unpublishedCourses)) {
            $dataToInsert = array();

            mtrace("Updating published status...");
            $unpublishedCourseIds = $this->get_field_array($unpublishedCourses);
            $unpublishedCourseObjects = $DB->get_records_list('course', 'id', $unpublishedCourseIds);
            foreach ($unpublishedCourseObjects as $course) {
                $time = time();
                $hash = $this->course_hash_service->get_course_state_hash($course);
                $fielddata = new \stdClass();
                $fielddata->fieldid = $customfieldid;
                $fielddata->instanceid = $course->id;
                $fielddata->value = $hash;
                $fielddata->valueformat = 1;
                $fielddata->timecreated = $time;
                $fielddata->timemodified = $time;

                $dataToInsert[] = $fielddata;
            }

            if (!empty($dataToInsert)) {
                $DB->insert_records('customfield_data', $dataToInsert);
            }
        }

        if (!empty($modifiedCourses)) {
            $existingCourseIds = $this->get_field_array($modifiedCourses);
            $existingCourseObjects = $DB->get_records_list('course', 'id', $existingCourseIds);
            $existingCourses = $this->to_id_to_object_dictionary($existingCourseObjects);

            $existingRecords = $DB->get_records_list('customfield_data', 'instanceid', $existingCourseIds);

            foreach ($existingRecords as $existingRecord) {
                $time = time();
                $existingCourse = $existingCourses[$existingRecord->instanceid];
                $hash = $this->course_hash_service->get_course_state_hash($existingCourse);
                $fielddata = new \stdClass();
                $fielddata->id = $existingRecord->id;
                $fielddata->value = $hash;
                $fielddata->timemodified = $time;

                $DB->update_record('customfield_data', $fielddata);
            }
        }
    }

    private function get_field_array($data, $keyField = 'id')
    {
        $result = array();
        foreach ($data as $item) {
            $field = $item[$keyField];
            $result[] = $field;
        }
        return $result;
    }

    private function to_id_to_object_dictionary($data)
    {
        $result = array();
        foreach ($data as $item) {
            $id = $item->id;
            $result[$id] = $item;
        }
        return $result;
    }
}