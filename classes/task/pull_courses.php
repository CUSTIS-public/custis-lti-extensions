<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;
use tool_ltiextensions\task\base\base_sync_job;

class pull_courses extends base_sync_job
{
    public function get_name()
    {
        return 'pull_courses';
    }

    public function do_work()
    {
    }
}