<?php

namespace App\Services;

use App\Enums\Priority;
use App\Exceptions\PriorityFullException;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place that changes task priority and order.
 *
 * Each priority is a column ordered by `position` (0 = top). P1 and P2 have a
 * capacity; to get a task into a full column, the user must name an occupant
 * that moves one level down. If that lower column is full as well, its last
 * task cascades further down automatically.
 */
class PriorityBoard
{
    /**
     * Open tasks in a priority column, top first.
     *
     * @return EloquentCollection<int, Task>
     */
    public function column(User $user, Priority $priority): EloquentCollection
    {
        return $user->tasks()
            ->open()
            ->where('priority', $priority)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create a task at the bottom of the backlog; follow with place() to give
     * it a real priority (which may need the "what moves down" choice).
     *
     * @param  array<string, mixed>  $attributes  fillable task attributes
     */
    public function createInBacklog(User $user, array $attributes): Task
    {
        $task = new Task($attributes);
        $task->forceFill([
            'user_id' => $user->id,
            'priority' => Priority::Backlog,
            'position' => $user->tasks()->open()->where('priority', Priority::Backlog)->count(),
        ])->save();

        return $task;
    }

    /**
     * Put a task into a priority column at the given index (end of the column
     * when null). Moving within the same column just reorders it.
     *
     * @throws PriorityFullException when the column is full and no valid $demote was given
     */
    public function place(Task $task, Priority $priority, ?int $index = null, ?Task $demote = null): void
    {
        DB::transaction(function () use ($task, $priority, $index, $demote) {
            $from = $task->priority;
            $column = $this->column($task->user, $priority)
                ->reject(fn (Task $t) => $t->is($task))
                ->values();

            if ($from !== $priority && $this->isFull($priority, $column)) {
                $demoteIndex = $demote ? $column->search(fn (Task $t) => $t->is($demote)) : false;

                if ($demoteIndex === false) {
                    throw new PriorityFullException($task, $priority, $index, $column);
                }

                // The incoming task takes the demoted task's spot unless told otherwise.
                $index ??= $demoteIndex;
                $column = $column->reject(fn (Task $t) => $t->is($demote))->values();

                // Leave the old column first so it has room if the demoted task lands there.
                $task->forceFill(['priority' => $priority])->save();
                $this->demote($demote);
            }

            $index = max(0, min($index ?? $column->count(), $column->count()));
            $column->splice($index, 0, [$task]);
            $task->priority = $priority;

            $this->persistOrder($column);

            if ($from !== $priority) {
                $this->compact($task->user, $from);
            }
        });
    }

    /**
     * Move a task one step up: within its column, or to the bottom of the
     * column above when it is already on top.
     *
     * @throws PriorityFullException when promoting into a full column
     */
    public function moveUp(Task $task): void
    {
        $index = $this->indexOf($task);

        if ($index > 0) {
            $this->place($task, $task->priority, $index - 1);
        } elseif ($higher = $task->priority->higher()) {
            $this->place($task, $higher);
        }
    }

    /**
     * Move a task one step down: within its column, or to the top of the
     * column below when it is already last.
     */
    public function moveDown(Task $task): void
    {
        $index = $this->indexOf($task);
        $count = $this->column($task->user, $task->priority)->count();

        if ($index < $count - 1) {
            $this->place($task, $task->priority, $index + 1);
        } else {
            $this->demote($task);
        }
    }

    /**
     * Push a task to the top of the next lower column, cascading the last
     * task of that column further down when it is full.
     */
    public function demote(Task $task): void
    {
        $lower = $task->priority->lower();

        if ($lower === null) {
            return;
        }

        $column = $this->column($task->user, $lower)->reject(fn (Task $t) => $t->is($task));
        $overflow = $this->isFull($lower, $column) ? $column->last() : null;

        $this->place($task, $lower, 0, $overflow);
    }

    public function complete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->forceFill(['completed_at' => now()])->save();
            $this->compact($task->user, $task->priority);
        });
    }

    /**
     * Reopen a finished task at the end of its old column, or of the first
     * lower column with room when the old one has filled up in the meantime.
     */
    public function reopen(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->forceFill(['completed_at' => null])->save();

            $priority = $task->priority;
            $others = fn (Priority $p) => $this->column($task->user, $p)->reject(fn (Task $t) => $t->is($task));

            while ($this->isFull($priority, $others($priority))) {
                $priority = $priority->lower();
            }

            $this->persistOrder($others($priority)->push($task->forceFill(['priority' => $priority])));
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->delete();
            $this->compact($task->user, $task->priority);
        });
    }

    /** Renumber a column so positions are 0..n-1 without gaps. */
    public function compact(User $user, Priority $priority): void
    {
        $this->persistOrder($this->column($user, $priority));
    }

    /**
     * @param  Collection<int, Task>  $others  column contents without the incoming task
     */
    private function isFull(Priority $priority, Collection $others): bool
    {
        $capacity = $priority->capacity();

        return $capacity !== null && $others->count() >= $capacity;
    }

    private function indexOf(Task $task): int
    {
        return (int) $this->column($task->user, $task->priority)
            ->search(fn (Task $t) => $t->is($task));
    }

    /**
     * Write priority + position for every task in the list. Always hits the
     * database because nested moves may have changed rows behind the backs of
     * the in-memory models.
     *
     * @param  Collection<int, Task>  $tasks
     */
    private function persistOrder(Collection $tasks): void
    {
        $tasks->values()->each(function (Task $task, int $position) {
            $task->position = $position;
            $task->newQuery()->whereKey($task->getKey())->update([
                'priority' => $task->priority,
                'position' => $position,
            ]);
            $task->syncOriginalAttributes(['priority', 'position']);
        });
    }
}
