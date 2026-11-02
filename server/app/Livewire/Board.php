<?php

namespace App\Livewire;

use App\Enums\Priority;
use App\Livewire\Concerns\InteractsWithTasks;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Priority')]
class Board extends Component
{
    use InteractsWithTasks;

    /** @return array<int, Collection<int, Task>> open tasks keyed by priority value */
    #[Computed]
    public function columns(): array
    {
        $tasks = Auth::user()->tasks()->open()
            ->with('project.company')
            ->withTrackedTime()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => $task->priority->value);

        return collect(Priority::cases())
            ->mapWithKeys(fn (Priority $p) => [$p->value => $tasks->get($p->value, collect())])
            ->all();
    }

    /** Called by wire:sort when a card is dropped. */
    public function sort(int|string $id, int $position, int|string $column): void
    {
        $task = $this->findTask((int) $id);

        $this->attempt(fn () => $this->board()->place($task, Priority::from((int) $column), $position));
    }
}
