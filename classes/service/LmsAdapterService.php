<?php

namespace tool_ltiextensions\service;

use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\ServiceRequest;
use enrol_lti\local\ltiadvantage\lib\http_client;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use moodle_url;
use tool_ltiextensions\str_utils;

class LmsAdapterService
{
    // Обертка над http_client для выполнения запросов к LmsAdapter.
    private LtiServiceConnector $ltiConnector;

    // Данные об LTI подключении между Moodle и LmsAdapter.
    // Является частью официального плагина "Publish as LTI tool" и заимствуется этим плагином для аутентификации/авторизации.
    private LtiRegistration $ltiRegistration;

    // Client scopes для авторизации в Keycloak.
    //TODO: актуализировать перед релизом.
    private array $requestScopes = ["https://modeus.org/lms/courses"];

    // Адрес адаптера
    private string $adapterUrl;

    // Загружает настройки и подготавливает клиент для выполнения запросов
    public function initialize()
    {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $appregistrationrepo = new application_registration_repository();
        $deploymentrepo = new deployment_repository();
        $issuerdb = new issuer_database($appregistrationrepo, $deploymentrepo);
        $appregistrations = $appregistrationrepo->find_all();
        $platformSettings = json_decode(get_config('tool_ltiextensions', 'platform_settings'));

        foreach ($appregistrations as $appregistration) {
            $deployments = $deploymentrepo->find_all_by_registration($appregistration->get_id());

            foreach ($deployments as $deployment) {
                $deplid = $deployment->get_deploymentid();
                $this->adapterUrl = str_utils::ensureSlash($platformSettings->$deplid->lmsapi);
                if ($this->adapterUrl === null) {
                    mtrace("WARNING: deployment with id $deplid doesn't have the 'lmsapi' property in platform_settings and will be SKIPPED");
                    continue;
                }

                $this->ltiRegistration = $issuerdb->findRegistrationByIssuer(
                    $appregistration->get_platformid()->out(false),
                    $appregistration->get_clientid()
                );
                $sesscache = new launch_cache_session();
                $this->ltiConnector = new LtiServiceConnector($sesscache, new http_client(new \curl()));

                break;
            }

            if ($this->ltiConnector !== null) {
                break;
            }
        }

        if ($this->ltiConnector === null) {
            throw new \Exception("Failed to create LtiServiceConnector: there are no deployments with configured platform_settings");
        }
    }

    // например, queryParams вида ['deploymentId' => $deployment->get_deploymentid()]
    public function httpGet(string $relativeUrl, array $queryParams): array
    {
        $url = new moodle_url($this->adapterUrl . $relativeUrl, $queryParams);
        $request = new ServiceRequest(
            ServiceRequest::METHOD_GET,
            $url,
            ServiceRequest::TYPE_UNSUPPORTED
        );

        return $this->requestAdapter($request);
    }

    public function httpPost(string $relativeUrl, array $queryParams, object $body): array
    {
        $url = new moodle_url($this->adapterUrl . $relativeUrl, $queryParams);
        $request = new ServiceRequest(
            ServiceRequest::METHOD_POST,
            $url,
            ServiceRequest::TYPE_UNSUPPORTED
        );
        $request->setBody(json_encode($body));

        return $this->requestAdapter($request);
    }

    // Выполняет запрос к адаптеру
    private function requestAdapter(ServiceRequest $request): array
    {
        mtrace("Request adapter {$request->getMethod()}: {$request->getUrl()}");

        // todo: $request->setAccept('application/json');  // нужно ли?
        $requestResult = $this->ltiConnector->makeServiceRequest($this->ltiRegistration, $this->requestScopes, $request, false);
        print_r($requestResult);

        return $requestResult;
    }
}