<?php

namespace tool_ltiextensions\task\base;

use core\task\scheduled_task;
use tool_ltiextensions\service\LmsAdapterService;

abstract class base_sync_job extends scheduled_task
{
    protected LmsAdapterService $lmsAdapterService;

    protected array $syncSessionTypeByTaskName = array(
        "pull_courses" => "PULL_COURSES",
        "push_courses" => "PUSH_COURSES",
        "pull_members" => "PULL_MEMBERS",
        "push_grades" => "PUSH_GRADES",
    );

    // Входная точка
    public function execute()
    {
        $current_task_name = $this->get_name();
        $syncSessionType = $this->syncSessionTypeByTaskName[$current_task_name];
        $started = time();
        $started_date = date('Y-m-d H:i:s', $started);

        mtrace('');
        mtrace("--- Current time: {$started_date}");
        mtrace("--- Initialising synchronization job: \"{$current_task_name}\"");
        $this->lmsAdapterService = new LmsAdapterService();

        mtrace('');
        mtrace("--- Getting last successful sync session of type '{$syncSessionType}'...");
        $lastClosedSession = $this->lmsAdapterService->getLastClosedSession($syncSessionType);
        if ($lastClosedSession === null) {
            mtrace("--- Last closed session was not found");
        } else {
            mtrace("--- Last closed sessionId: {$lastClosedSession['id']}");
        }

        mtrace("--- Executing work precheck...");
        if (!$this->work_precheck($lastClosedSession)) {
            mtrace("--- Work precheck failed. Skipping sync work;");
            $this->log_results($started);
            return;
        } else {
            mtrace("--- Work precheck succeed.");
        }

        mtrace("--- Creating sync session of type '{$syncSessionType}'...");
        $currentSession = $this->lmsAdapterService->openSession($syncSessionType);
        mtrace("--- Opened sessionId: {$currentSession['id']}");

        mtrace('');
        mtrace("--- Starting job...");
        $this->do_work($currentSession, $lastClosedSession);
        mtrace("--- Job is done!");

        mtrace('');
        mtrace("--- Closing sync session...");
        $this->lmsAdapterService->closeSession($currentSession['id']);
        $this->log_results($started);
    }

    private function log_results($started)
    {
        $duration = time() - $started;
        $duration_date = gmdate('H:i:s', $duration);
        mtrace('');
        mtrace('--- Job total time: ' . $duration_date);
    }

    // Нужна ли (возможна ли) синхронизация
    public function work_precheck(?array $lastClosedSession): bool
    {
        return true;
    }

    // Выполнение работы по синхронизации
    abstract public function do_work(array $currentSession, ?array $lastClosedSession);

    protected function epochFromSession(?array $session): ?string
    {
        if ($session === null) {
            return null;
        }
        $dateString = $session['externalCreatedAt'];
        $epochTime = (new \DateTime($dateString, new \DateTimeZone('UTC')))->format('U');

        return $epochTime;
    }

    protected function epochToDateString($epoch): string
    {
        $dt = new \DateTime("@$epoch");
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('c');
    }
}
