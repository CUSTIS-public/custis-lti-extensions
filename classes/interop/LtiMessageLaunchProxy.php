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

use Packback\Lti1p3\LtiMessageLaunch;

/**
 * Обертка, чтобы передать подхаченный LtiMessageLaunch.
 * В обычном LTI мы получаем LtiMessageLaunch из jwt-токена (в $data попадает поле body), 
 * однако в джобе start_lti_sync нам приходят нужные данные из сервиса.
 * Мы берем и подсовываем эти данные в сообщение.
 *
 * @package    tool_ltiextensions
 * @copyright  2023 CUSTIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class LtiMessageLaunchProxy extends LtiMessageLaunch
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getLaunchData()
    {
        return $this->data;
    }
}