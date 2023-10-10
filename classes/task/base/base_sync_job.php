<?php

namespace tool_ltiextensions\task\base;

use core\task\scheduled_task;

abstract class base_sync_job extends scheduled_task
{
    public function execute()
    {
        $started = time();
        $started_date = date('Y-m-d H:i:s', $started);
        mtrace('');
        mtrace("--- Initialising synchronization job: \"{$this->get_name()}\"");
        mtrace("--- Current time: {$started_date}");
        mtrace("--- Starting job...");
        mtrace('');

        $this->do_work();

        $duration = time() - $started;
        $duration_date = gmdate('H:i:s', $duration);
        mtrace('');
        mtrace('--- Job total time: ' . $duration_date);
    }

    public abstract function do_work();
}