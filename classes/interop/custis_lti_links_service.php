<?php
//    Moodle LTI Extensions
//    Copyright (C) 2023 CUSTIS
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_ltiextensions\interop;

use Packback\Lti1p3\LtiAbstractService;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\ServiceRequest;

class custis_lti_links_service extends LtiAbstractService
{
    public const CONTENTTYPE_LINKSCONTAINER = 'application/json';

    public function getScope(): array
    {
        return ["https://modeus.org/lms/links.readonly"];
    }

    public function getLinks(): array
    {
        $request = new ServiceRequest(
            ServiceRequest::METHOD_GET,
            $this->getServiceData()['context_links_url'],
            "get_links"
        );
        $request->setAccept(static::CONTENTTYPE_LINKSCONTAINER);

        return $this->getAll($request, 'links');
    }
}
