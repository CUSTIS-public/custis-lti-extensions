<?php

namespace tool_ltiextensions\task\base;

use core\task\scheduled_task;
use tool_ltiextensions\service\LmsAdapterService;

abstract class base_sync_job extends scheduled_task
{
    protected LmsAdapterService $lmsAdapterService;

    private array $syncSessionTypeByTaskName = array(
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
        mtrace("--- Creating sync session of type '{$syncSessionType}'...");
        $lastClosedSession = $this->lmsAdapterService->getLastClosedSession($syncSessionType);
        $currentSession = $this->lmsAdapterService->openSession($syncSessionType); // TODO: Создавать сессию синхронизации, только если есть, что синхронизировать (реализовать предпроверку)
        if ($lastClosedSession === null) {
            mtrace("--- Last closed session was not found");
        } else {
            mtrace("--- Last closed sessionId: {$lastClosedSession['id']}");
        }
        mtrace("--- Opened sessionId: {$currentSession['id']}");

        mtrace('');
        mtrace("--- Starting job...");
        $this->do_work($currentSession, $lastClosedSession);
        mtrace("--- Job is done!");

        mtrace('');
        mtrace("--- Closing sync session...");
        $this->lmsAdapterService->closeSession($currentSession['id']);

        $duration = time() - $started;
        $duration_date = gmdate('H:i:s', $duration);
        mtrace('');
        mtrace('--- Job total time: ' . $duration_date);
    }

    // Выполнение работы по синхронизации
    abstract public function do_work(array $currentSession, ?array $lastClosedSession);

    protected function epochFromSession(?array $session): ?int
    {
        if ($session === null) {
            return null;
        }
        $dateString = $session['externalCreatedAt'];
        $epochTime = (new \DateTime($dateString, new \DateTimeZone('UTC')))->format('U');

        return $epochTime;
    }
}
