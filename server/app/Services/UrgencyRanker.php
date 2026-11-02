<?php

namespace App\Services;

use App\Enums\Priority;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Decides what is most urgent right now (dashboard, widget, daily e-mail).
 *
 * Order: overdue / due today, then P1 in board order, then anything due
 * within a few days, then P2, P3 and backlog in board order.
 */
class UrgencyRanker
{
    public const OVERDUE_OR_TODAY = 0;

    public const P1 = 1;

    public const DUE_SOON = 2;

    /**
     * @return Collection<int, Task>
     */
    public function top(User $user, int $limit = 10): Collection
    {
        $today = today();
        $soon = today()->addDays(config('prio.due_soon_days'));

        return $user->tasks()
            ->open()
            ->with('project.company')
            ->withTrackedTime()
            ->get()
            ->sortBy(function (Task $task) use ($today, $soon) {
                $tier = $this->tier($task, $today, $soon);
                $byDueDate = in_array($tier, [self::OVERDUE_OR_TODAY, self::DUE_SOON], true);

                return [
                    $tier,
                    $byDueDate ? $task->due_date->timestamp : 0,
                    $task->priority->value,
                    $task->position,
                ];
            })
            ->take($limit)
            ->values();
    }

    public function tier(Task $task, ?CarbonInterface $today = null, ?CarbonInterface $soon = null): int
    {
        $today ??= today();
        $soon ??= today()->addDays(config('prio.due_soon_days'));

        return match (true) {
            $task->due_date?->lte($today) => self::OVERDUE_OR_TODAY,
            $task->priority === Priority::P1 => self::P1,
            $task->due_date?->lte($soon) => self::DUE_SOON,
            default => $task->priority->value + 1,
        };
    }
}
