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

    public function getLastClosedSession(string $syncSessionType): array
    {
        $response = $this->lmsHttpClient->httpGet("api/v1/lms/{$this->lmsId}/sync-sessions/last-closed", ['type' => $syncSessionType]);

        return $response['body'];
    }

    public function openSession(string $syncSessionType): array
    {
        $body = new \stdClass();
        $body->syncSessionType = $syncSessionType;
        $body->externalCreatedAt = date('c');
        $response = $this->lmsHttpClient->httpPost("api/v1/lms/{$this->lmsId}/sync-sessions", [], $body);

        return $response['body'];
    }

    public function closeSession(string $id)
    {
        $this->lmsHttpClient->httpPost("api/v1/lms/{$this->lmsId}/sync-sessions/$id/close", [], null);
    }

    public function getCoursesToCreate(string $sessionId): array
    {
        mtrace("Getting courses for creation from adapter...");
        return $this->lmsHttpClient->httpGet("api/v1/lms/{$this->lmsId}/sync-sessions/{$sessionId}/sync/courses", [])['body'];
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
