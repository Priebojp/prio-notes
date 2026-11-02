<?php

namespace App\Digest;

use App\Enums\ActionItemStatus;
use App\Enums\Priority;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\UrgencyRanker;
use Carbon\CarbonImmutable;

class DigestBuilder
{
    /** How many tasks the "today" list shows beyond everything overdue or due today. */
    public const FOCUS_SIZE = 5;

    public const UPCOMING_DAYS = 7;

    public function __construct(private readonly UrgencyRanker $ranker) {}

    public function build(User $user, ?CarbonImmutable $date = null): DigestData
    {
        $date ??= CarbonImmutable::today();

        $ranked = $this->ranker->top($user, PHP_INT_MAX);
        $focus = $ranked
            ->filter(fn (Task $task, int $i) => $i < self::FOCUS_SIZE
                || $this->ranker->tier($task) === UrgencyRanker::OVERDUE_OR_TODAY)
            ->values();

        $upcoming = $ranked
            ->filter(fn (Task $task) => $task->due_date !== null
                && $task->due_date->gt($date)
                && $task->due_date->lte($date->addDays(self::UPCOMING_DAYS))
                && ! $focus->contains($task))
            ->sortBy(fn (Task $task) => $task->due_date->timestamp)
            ->values();

        $trackedYesterday = $user->timeEntries()
            ->with('task.project.company')
            ->whereBetween('started_at', [$date->subDay()->startOfDay(), $date->subDay()->endOfDay()])
            ->whereNotNull('seconds')
            ->get()
            ->groupBy(fn (TimeEntry $e) => $e->task->project
                ? $e->task->project->company->name.' · '.$e->task->project->name
                : 'Bez projektu')
            ->map(fn ($entries) => (int) $entries->sum('seconds'))
            ->sortDesc();

        $pending = fn ($q) => $q->where('status', ActionItemStatus::Pending);
        $meetingsToReview = $user->meetings()
            ->whereHas('actionItems', $pending)
            ->withCount(['actionItems as pending_items_count' => $pending])
            ->latest('held_at')
            ->get();

        return new DigestData(
            user: $user,
            date: $date,
            focus: $focus,
            upcoming: $upcoming,
            overdueCount: $ranked->filter(fn (Task $t) => $t->due_date?->lt($date) === true)->count(),
            dueTodayCount: $ranked->filter(fn (Task $t) => $t->due_date?->isSameDay($date) === true)->count(),
            p1Count: $ranked->filter(fn (Task $t) => $t->priority === Priority::P1)->count(),
            p1Capacity: Priority::P1->capacity(),
            trackedYesterday: $trackedYesterday,
            meetingsToReview: $meetingsToReview,
        );
    }
}
