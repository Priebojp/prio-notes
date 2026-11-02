<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithTasks;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithTasks;

    public Project $project;

    public string $name = '';

    public function mount(Project $project): void
    {
        abort_unless($project->user_id === Auth::id(), 404);

        $this->project = $project;
        $this->name = $project->name;
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function openTasks(): Collection
    {
        return $this->project->tasks()->open()->with('project.company')
            ->orderBy('priority')->orderBy('position')->get();
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function doneTasks(): Collection
    {
        return $this->project->tasks()->done()->with('project.company')
            ->latest('completed_at')->limit(20)->get();
    }

    public function rename(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255']]);
        $this->project->update(['name' => $this->name]);

        Flux::modal('project-settings')->close();
    }

    public function toggleArchive(): void
    {
        $this->project->update(['archived_at' => $this->project->archived_at ? null : now()]);

        Flux::modal('project-settings')->close();
    }

    public function render(): View
    {
        return view('livewire.projects.show')->title($this->project->name);
    }
}
