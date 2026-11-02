<?php

namespace App\Livewire\Concerns;

use App\Enums\Priority;
use App\Exceptions\PriorityFullException;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\PriorityBoard;
use App\Services\TimeTracker;
use Closure;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Shared task actions for pages that list tasks: completing, ordering,
 * timers, and the "column is full, what moves down?" dialog.
 *
 * Views using this trait must include livewire.partials.priority-conflict.
 */
trait InteractsWithTasks
{
    public ?int $conflictTaskId = null;

    public ?int $conflictPriority = null;

    public ?int $conflictIndex = null;

    public ?int $demoteId = null;

    #[On('tasks-changed')]
    #[On('timer-changed')]
    public function refreshTasks(): void
    {
        unset($this->runningEntry);
    }

    public function complete(int $id): void
    {
        $task = $this->findTask($id);

        app(TimeTracker::class)->stopTask($task);
        $this->board()->complete($task);
        $this->dispatch('tasks-changed');
        $this->dispatch('timer-changed');
    }

    public function startTimer(int $id): void
    {
        app(TimeTracker::class)->start($this->findTask($id));
        $this->dispatch('timer-changed');
    }

    public function stopTimer(): void
    {
        app(TimeTracker::class)->stop(Auth::user());
        $this->dispatch('timer-changed');
    }

    #[Computed]
    public function runningEntry(): ?TimeEntry
    {
        return app(TimeTracker::class)->running(Auth::user());
    }

    public function reopen(int $id): void
    {
        $this->board()->reopen($this->findTask($id));
        $this->dispatch('tasks-changed');
    }

    public function moveUp(int $id): void
    {
        $this->attempt(fn () => $this->board()->moveUp($this->findTask($id)));
    }

    public function moveDown(int $id): void
    {
        $this->attempt(fn () => $this->board()->moveDown($this->findTask($id)));
    }

    /** @return EloquentCollection<int, Task> */
    #[Computed]
    public function conflictOccupants(): EloquentCollection
    {
        if ($this->conflictPriority === null) {
            return new EloquentCollection;
        }

        return $this->board()
            ->column(Auth::user(), Priority::from($this->conflictPriority))
            ->reject(fn (Task $task) => $task->id === $this->conflictTaskId)
            ->load('project.company');
    }

    public function conflictModalName(): string
    {
        return 'priority-conflict-'.$this->getId();
    }

    public function resolveConflict(): void
    {
        $this->validate(['demoteId' => 'required|integer'], ['demoteId.required' => 'Vyber úlohu, ktorá pôjde nižšie.']);

        $this->board()->place(
            $this->findTask($this->conflictTaskId),
            Priority::from($this->conflictPriority),
            $this->conflictIndex,
            $this->findTask($this->demoteId),
        );

        $this->cancelConflict();
        $this->dispatch('tasks-changed');
        Flux::toast(variant: 'success', text: 'Priority upravené.');
    }

    public function cancelConflict(): void
    {
        Flux::modal($this->conflictModalName())->close();
        $this->reset('conflictTaskId', 'conflictPriority', 'conflictIndex', 'demoteId');
        $this->resetValidation();
    }

    /**
     * Run a board change; when the target column is full, open the dialog
     * asking what to demote instead of failing.
     */
    protected function attempt(Closure $change): bool
    {
        try {
            $change();
            $this->dispatch('tasks-changed');

            return true;
        } catch (PriorityFullException $e) {
            $this->conflictTaskId = $e->task->id;
            $this->conflictPriority = $e->priority->value;
            $this->conflictIndex = $e->index;
            $this->demoteId = null;
            unset($this->conflictOccupants);

            Flux::modal($this->conflictModalName())->show();

            return false;
        }
    }

    protected function findTask(?int $id): Task
    {
        return Auth::user()->tasks()->findOrFail($id);
    }

    protected function board(): PriorityBoard
    {
        return app(PriorityBoard::class);
    }
}
