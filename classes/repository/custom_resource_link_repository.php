<?php
namespace tool_ltiextensions\repository;

use enrol_lti\local\ltiadvantage\entity\resource_link;

/**
 * Дополнительный репозиторий для resource_link
 * 
 * Методы маппинга скопированы из ```enrol/lti/classes/local/ltiadvantage/repository/resource_link_repository.php```
 */
class custom_resource_link_repository
{
    private $resourcelinktable = 'enrol_lti_resource_link';

    /**
     * Получить все существующие resource links.
     */
    public function find_all_resource_links(): array
    {
        global $DB;
        return $this->resource_links_from_records($DB->get_records($this->resourcelinktable));
    }

    /**
     * Get a list of resource_link objects from a list of records.
     *
     * @param array $records the list of records to transform.
     * @return array the array of resource_link instances.
     */
    private function resource_links_from_records(array $records): array
    {
        $resourcelinks = [];
        foreach ($records as $record) {
            $resourcelinks[] = $this->resource_link_from_record($record);
        }
        return $resourcelinks;
    }

    /**
     * Convert a record into an object and return it.
     *
     * @param \stdClass $record the record from the store.
     * @return resource_link a resource_link object.
     */
    private function resource_link_from_record(\stdClass $record): resource_link
    {
        $resourcelink = resource_link::create(
            $record->resourcelinkid,
            $record->ltideploymentid,
            $record->resourceid,
            $record->lticontextid,
            $record->id
        );

        if ($record->lineitemsservice || $record->lineitemservice) {
            $scopes = [];
            if ($record->lineitemscope) {
                $lineitemscopes = json_decode($record->lineitemscope);
                foreach ($lineitemscopes as $lineitemscope) {
                    $scopes[] = $lineitemscope;
                }
            }
            if ($record->resultscope) {
                $scopes[] = $record->resultscope;
            }
            if ($record->scorescope) {
                $scopes[] = $record->scorescope;
            }
            $resourcelink->add_grade_service(
                $record->lineitemsservice ? new \moodle_url($record->lineitemsservice) : null,
                $record->lineitemservice ? new \moodle_url($record->lineitemservice) : null,
                $scopes
            );
        }

        if ($record->contextmembershipsurl) {
            $resourcelink->add_names_and_roles_service(
                new \moodle_url($record->contextmembershipsurl),
                json_decode($record->nrpsserviceversions)
            );
        }

        return $resourcelink;
    }
}