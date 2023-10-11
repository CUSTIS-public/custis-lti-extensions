<?php

namespace tool_ltiextensions\task\base;

use core\task\scheduled_task;
use tool_ltiextensions\service\LmsAdapterService;

abstract class base_sync_job extends scheduled_task
{
    protected LmsAdapterService $lmsAdapterService;

    // Имя текущей задачи
    private string $current_task_name;

    // Входная точка
    public function execute()
    {
        $this->current_task_name = $this->get_name();
        $started = time();
        $started_date = date('Y-m-d H:i:s', $started);

        mtrace('');
        mtrace("--- Current time: {$started_date}");
        mtrace("--- Initialising synchronization job: \"{$this->current_task_name}\"");

        $this->lmsAdapterService = new LmsAdapterService();

        mtrace("--- Starting job...");
        mtrace('');

        $this->do_work();

        $duration = time() - $started;
        $duration_date = gmdate('H:i:s', $duration);
        mtrace('');
        mtrace('--- Job total time: ' . $duration_date);
    }

    // Выполнение работы по синхронизации
    abstract public function do_work();
}
