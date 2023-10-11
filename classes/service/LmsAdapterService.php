<?php

namespace tool_ltiextensions\service;

// Сервис, получающий данные из LmsAdapter
class LmsAdapterService
{
    private LmsAdapterHttpClient $lmsHttpClient;

    private string $lmsId;

    function __construct()
    {
        $this->lmsHttpClient = new LmsAdapterHttpClient();
        $this->lmsHttpClient->initialize();
        $this->lmsId = $this->getLmsId();
    }

    public function getCoursesToCreate()
    {
        mtrace("Getting courses for creation from adapter...");
        // todo: $response = $this->lmsHttpClient->httpGet('api/v1/lms/by-deployment/' . $this->lmsHttpClient->deploymentId, []);
    }

    private function getLmsId(): string
    {
        mtrace("Getting lmsId from adapter...");
        $response = $this->lmsHttpClient->httpGet('api/v1/lms/by-deployment/' . $this->lmsHttpClient->deploymentId, []);
        $lmsId = $response['body']['id'];
        mtrace("LmsId: $lmsId");

        return $lmsId;
    }
}
