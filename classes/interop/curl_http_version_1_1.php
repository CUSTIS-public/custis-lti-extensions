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


/** CURL, который посылает все запросы в версии HTTP 1.1. См. MODEUSSW-19250 */
class curl_http_version_1_1 extends \curl
{
    public function resetopt()
    {
        parent::resetopt();
        parent::setopt(['CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1]);
    }
}
