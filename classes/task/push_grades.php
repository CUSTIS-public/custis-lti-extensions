<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;
use tool_ltiextensions\task\base\base_sync_job;

class push_grades extends base_sync_job
{
    public function get_name()
    {
        return 'push_grades';
    }

    public function do_work()
    {
    }
}