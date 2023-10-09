<?php

namespace tool_ltiextensions\service;

use Packback\Lti1p3\LtiAbstractService;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\ServiceRequest;

class custis_lti_pull_courses_service extends LtiAbstractService
{
    public const CONTENTTYPE_LINKSCONTAINER = 'application/json';

    public function getScope(): array
    {
        return ["https://modeus.org/lms/courses.readonly"];
    }

    public function getCourses(): array
    {
        $request = new ServiceRequest(
            ServiceRequest::METHOD_GET,
            $this->getServiceData()['url'],
            "pull_courses"
        );
        $request->setAccept(static::CONTENTTYPE_LINKSCONTAINER);

        return $this->getAll($request);
    }
}