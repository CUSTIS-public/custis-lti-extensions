<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;

class push_grades extends scheduled_task
{
    public function get_name()
    {
        return 'push_grades';
    }

    public function execute()
    {
        $started = time();
        mtrace('Starting job...');

        $duration = time() - $started;
        mtrace('Job completed in: ' . $duration . ' seconds');
    }
}