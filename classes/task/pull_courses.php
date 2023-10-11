<?php

namespace tool_ltiextensions\task;

use tool_ltiextensions\task\base\base_sync_job;

class pull_courses extends base_sync_job
{
    public function get_name()
    {
        return 'pull_courses';
    }

    public function do_work()
    {
        $categoryId = get_config('tool_ltiextensions', 'default_category');
        if (!$categoryId) {
            throw new \Exception("Не задана настройка default_category");
        }

        $this->lmsAdapterService->getCoursesToCreate();
    }
}
