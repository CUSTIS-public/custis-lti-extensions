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

namespace tool_ltiextensions;

use Throwable;

class debug_utils
{
    /** Нужно ли показывать отладочную информацию? */
    public static function shouldShowDebugInfo()
    {
        global $CFG;
        $hasdebugdeveloper = (isset($CFG->debugdisplay) &&
            isset($CFG->debug) &&
            $CFG->debugdisplay &&
            ($CFG->debug === DEBUG_DEVELOPER || $CFG->debug === DEBUG_ALL)
        );

        return $hasdebugdeveloper;
    }

    public static function traceError(Throwable $e)
    {
        mtrace($e->getMessage());
        if (debug_utils::shouldShowDebugInfo()) {
            mtrace($e->getTraceAsString());
        }
    }
}
