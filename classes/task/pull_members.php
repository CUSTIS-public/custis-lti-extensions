<?php

namespace tool_ltiextensions\task;

use core\task\scheduled_task;

class pull_members extends scheduled_task
{
    public function get_name()
    {
        return 'pull_members';
    }

    public function execute()
    {
        $started = time();
        mtrace('Starting job...');

        $duration = time() - $started;
        mtrace('Job completed in: ' . $duration . ' seconds');
    }
}