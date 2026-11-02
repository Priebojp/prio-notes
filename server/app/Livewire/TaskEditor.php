<?php

namespace App\Livewire;

use App\Enums\Priority;
use App\Livewire\Concerns\InteractsWithTasks;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Create / edit dialog, mounted once in the app layout and opened with the
 * browser event `edit-task` ({ id } to edit, { projectId } to preselect).
 */
class TaskEditor extends Component
{
    use InteractsWithTasks;

    public ?int $taskId = null;

    public string $title = '';

    public string $notes = '';

    public ?int $projectId = null;

    public ?string $dueDate = null;

    public int $priority = 3;

    public ?int $estimateMinutes = null;

    #[On('edit-task')]
    public function open(?int $id = null, ?int $projectId = null): void
    {
        $this->resetValidation();
        $this->reset('taskId', 'title', 'notes', 'projectId', 'dueDate', 'priority', 'estimateMinutes');

        if ($id !== null) {
            $task = $this->findTask($id);
            $this->taskId = $task->id;
            $this->title = $task->title;
            $this->notes = (string) $task->notes;
            $this->projectId = $task->project_id;
            $this->dueDate = $task->due_date?->toDateString();
            $this->priority = $task->priority->value;
            $this->estimateMinutes = $task->estimate_minutes;
        } else {
            $this->projectId = $projectId;
        }

        Flux::modal('task-editor')->show();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'projectId' => ['nullable', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'dueDate' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'estimateMinutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $task = $this->taskId ? $this->findTask($this->taskId) : new Task;
        $task->forceFill(['user_id' => Auth::id()])->fill([
            'title' => $data['title'],
            'notes' => $data['notes'] ?: null,
            'project_id' => $data['projectId'],
            'due_date' => $data['dueDate'],
            'estimate_minutes' => $data['estimateMinutes'],
        ]);
        if (! $task->exists) {
            // New tasks start at the bottom of the backlog and are placed below.
            $task->position = Auth::user()->tasks()->open()->where('priority', Priority::Backlog)->count();
        }
        $task->save();

        Flux::modal('task-editor')->close();

        $placed = $this->attempt(fn () => $this->board()->place($task, Priority::from($this->priority)));

        $this->dispatch('tasks-changed');
        if ($placed) {
            Flux::toast(variant: 'success', text: 'Úloha uložená.');
        }
    }

    public function delete(): void
    {
        $this->board()->delete($this->findTask($this->taskId));

        Flux::modal('task-editor')->close();
        $this->dispatch('tasks-changed');
        Flux::toast(text: 'Úloha zmazaná.');
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function projects(): Collection
    {
        return Auth::user()->projects()->active()->with('company')->get()
            ->sortBy(fn (Project $p) => $p->company->name.' '.$p->name);
    }
}
