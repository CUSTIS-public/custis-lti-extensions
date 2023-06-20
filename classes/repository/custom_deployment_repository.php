<?php
namespace tool_ltiextensions\repository;

use enrol_lti\local\ltiadvantage\entity\deployment;

/**
 * Дополнительный репозиторий для deployment
 * 
 * Методы маппинга скопированы из ```enrol/lti/classes/local/ltiadvantage/repository/deployment_repository.php```
 */
class custom_deployment_repository
{
    private $deploymenttable = 'enrol_lti_deployment';

    /**
     * Получить все существующие deployments.
     */
    public function find_all_deployments(): array
    {
        global $DB;
        return $this->deployments_from_records($DB->get_records($this->deploymenttable));
    }

    /**
     * Create a list of deployments based on a list of records.
     *
     * @param array $records an array of deployment records.
     * @return deployment[]
     */
    private function deployments_from_records(array $records): array
    {
        if (empty($records)) {
            return [];
        }
        return array_map(function ($record) {
            return $this->deployment_from_record($record);
        }, $records);
    }

    /**
     * Create a valid deployment from a record.
     *
     * @param \stdClass $record the record.
     * @return deployment the deployment instance.
     */
    private function deployment_from_record(\stdClass $record): deployment
    {
        $deployment = deployment::create(
            $record->platformid,
            $record->deploymentid,
            $record->name,
            $record->id,
            $record->legacyconsumerkey
        );
        return $deployment;
    }
}