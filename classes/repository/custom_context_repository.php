<?php
namespace tool_ltiextensions\repository;

use enrol_lti\local\ltiadvantage\entity\context;

/**
 * Дополнительный репозиторий для context
 * 
 * Методы маппинга скопированы из ```enrol/lti/classes/local/ltiadvantage/repository/context_repository.php```
 */
class custom_context_repository
{
    private $lticontexttable = 'enrol_lti_context';

    /**
     * Получить все существующие LTI contexts.
     */
    public function find_all_lti_contexts(): array
    {
        global $DB;
        return $this->contexts_from_records($DB->get_records($this->lticontexttable));
    }

    /**
     * Get a list of context objects from a list of records.
     *
     * @param array $records the list of records to transform.
     * @return array the array of context instances.
     */
    private function contexts_from_records(array $records): array
    {
        $contexts = [];
        foreach ($records as $record) {
            $contexts[] = $this->context_from_record($record);
        }
        return $contexts;
    }

    /**
     * Generate a context instance from a record.
     *
     * @param \stdClass $record the record.
     * @return context the context instance.
     */
    private function context_from_record(\stdClass $record): context
    {
        $context = context::create(
            $record->ltideploymentid,
            $record->contextid,
            json_decode($record->type),
            $record->id
        );
        return $context;
    }
}