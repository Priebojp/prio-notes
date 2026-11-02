<?php

namespace App\Livewire;

use App\Enums\Priority;
use App\Livewire\Concerns\InteractsWithTasks;
use App\Models\Task;
use App\Services\UrgencyRanker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Teraz')]
class Now extends Component
{
    use InteractsWithTasks;

    public string $quickTitle = '';

    public int $quickPriority = 3;

    /** @return Collection<int, Task> */
    #[Computed]
    public function tasks(): Collection
    {
        return app(UrgencyRanker::class)->top(Auth::user(), 15);
    }

    /** @return array<int, int> open task count per priority */
    #[Computed]
    public function counts(): array
    {
        return Auth::user()->tasks()->open()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->all();
    }

    #[Computed]
    public function doneToday(): int
    {
        return Auth::user()->tasks()->where('completed_at', '>=', today())->count();
    }

    public function quickAdd(): void
    {
        $this->validate([
            'quickTitle' => ['required', 'string', 'max:255'],
            'quickPriority' => ['required', 'integer', 'between:1,4'],
        ], ['quickTitle.required' => 'Napíš, čo treba spraviť.']);

        $task = $this->board()->createInBacklog(Auth::user(), ['title' => $this->quickTitle]);

        $this->reset('quickTitle');
        $this->attempt(fn () => $this->board()->place($task, Priority::from($this->quickPriority)));
    }
}
