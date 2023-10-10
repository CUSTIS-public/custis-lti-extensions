<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;
use tool_ltiextensions\task\base\base_sync_job;

class pull_members extends base_sync_job
{
    public function get_name()
    {
        return 'pull_members';
    }

    public function do_work()
    {
    }
}