<?php

namespace tool_ltiextensions\service;

use Packback\Lti1p3\LtiAbstractService;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\ServiceRequest;

class custis_lti_post_courses_service extends LtiAbstractService
{
    public const CONTENTTYPE_LINKSCONTAINER = 'application/json';

    public function getScope(): array
    {
        return ["https://modeus.org/lms/courses"];
    }

    public function postCourses(array $courses): array
    {
        $request = new ServiceRequest(
            ServiceRequest::METHOD_POST,
            $this->getServiceData()['url'],
            'push_courses'
        );
        $request->setAccept(static::CONTENTTYPE_LINKSCONTAINER);
        $request->setBody(json_encode($courses));

        return $this->makeServiceRequest($request);
    }
}
