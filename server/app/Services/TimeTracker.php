<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Start/stop timers on tasks. A user has at most one running timer;
 * starting a new one stops the previous one.
 */
class TimeTracker
{
    public function running(User $user): ?TimeEntry
    {
        return $user->timeEntries()->running()->with('task.project.company')->latest('started_at')->first();
    }

    public function start(Task $task): TimeEntry
    {
        return DB::transaction(function () use ($task) {
            $running = $this->running($task->user);

            if ($running?->task_id === $task->id) {
                return $running;
            }

            $this->stop($task->user);

            $entry = new TimeEntry;
            $entry->forceFill([
                'user_id' => $task->user_id,
                'task_id' => $task->id,
                'started_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function stop(User $user): ?TimeEntry
    {
        $entry = $user->timeEntries()->running()->first();
        $entry?->finish(now());

        return $entry;
    }

    /** Stop the timer only if it is running on this task. */
    public function stopTask(Task $task): void
    {
        $task->timeEntries()->running()->first()?->finish(now());
    }

    /**
     * Log time after the fact. Entries for today end now, entries for past
     * days end at 17:00 so they sort sensibly in the day's list.
     */
    public function log(Task $task, int $minutes, CarbonInterface $day, ?string $note = null): TimeEntry
    {
        $end = ($day->isToday() ? now() : $day->copy()->setTime(17, 0))->toImmutable();

        $entry = new TimeEntry(['note' => $note]);
        $entry->forceFill([
            'user_id' => $task->user_id,
            'task_id' => $task->id,
            'started_at' => $end->subMinutes($minutes),
            'ended_at' => $end,
            'seconds' => $minutes * 60,
        ])->save();

        return $entry;
    }
}
