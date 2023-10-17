<?php

namespace tool_ltiextensions\service;

use Packback\Lti1p3\Interfaces\IHttpException;

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

    public function getLastClosedSession(string $syncSessionType): ?array
    {
        try {
            $response = $this->lmsHttpClient->httpGet("api/v1/lms/{$this->lmsId}/sync-sessions/last-closed", ['type' => $syncSessionType]);
        } catch (IHttpException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status == 404) {
                return null;
            }
        }

        return $response['body'];
    }

    public function openSession(string $syncSessionType): array
    {
        $requestBody = new \stdClass();
        $requestBody->syncSessionType = $syncSessionType;
        $currentDate = new \DateTime();
        $currentDate->setTimezone(new \DateTimeZone('UTC'));
        $requestBody->externalCreatedAt = $currentDate->format('c');
        $response = $this->lmsHttpClient->httpPost("api/v1/lms/{$this->lmsId}/sync-sessions", [], $requestBody);

        return $response['body'];
    }

    public function closeSession(string $id)
    {
        $this->lmsHttpClient->httpPost("api/v1/lms/{$this->lmsId}/sync-sessions/$id/close", [], null);
    }

    private function getLmsId(): string
    {
        mtrace("Getting lmsId from adapter...");
        $response = $this->lmsHttpClient->httpGet('api/v1/lms/by-deployment/' . $this->lmsHttpClient->deploymentId, []);
        $lmsId = $response['body']['id'];
        mtrace("LmsId: $lmsId");

        return $lmsId;
    }

    public function getCoursesToCreate(string $sessionId): array
    {
        mtrace("Getting courses for creation from adapter...");
        return $this->lmsHttpClient->httpGet("api/v1/lms/{$this->lmsId}/sync-sessions/{$sessionId}/sync/courses", [])['body'];
    }

    public function updateCoursesAndModuleTypes(string $sessionId, object $requestBody)
    {
        mtrace("Sending courses and module types to adapter...");
        $this->lmsHttpClient->httpPost("api/v1/lms/{$this->lmsId}/sync-sessions/{$sessionId}/sync/courses", [], $requestBody);
    }
}
