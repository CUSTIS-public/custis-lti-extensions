<?php

namespace tool_ltiextensions\service;

use Packback\Lti1p3\LtiAbstractService;
use Packback\Lti1p3\ServiceRequest;

class audit_event_logging_service extends LtiAbstractService
{
    public const CONTENTTYPE = 'application/json';

    public function getScope(): array
    {
        return ["https://modeus.org/lms/courses"];
    }

    public function confirm_membership_sync(): void
    {
        $request = new ServiceRequest(
            ServiceRequest::METHOD_POST,
            $this->getServiceData()['url'],
            'confirm_membership_sync'
        );
        $request->setAccept(static::CONTENTTYPE);

        $this->makeServiceRequest($request);
    }
}
