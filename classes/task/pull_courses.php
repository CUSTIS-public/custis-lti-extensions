<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;

class pull_courses extends scheduled_task
{
    public function get_name()
    {
        return 'pull_courses';
    }

    public function execute()
    {
        $started = time();
        mtrace('Starting job...');

        $duration = time() - $started;
        mtrace('Job completed in: ' . $duration . ' seconds');
    }
}