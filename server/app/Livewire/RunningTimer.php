<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Services\TimeTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/** Floating bar showing the running timer on every page. */
class RunningTimer extends Component
{
    #[On('timer-changed')]
    #[On('tasks-changed')]
    public function refresh(): void
    {
        unset($this->entry);
    }

    #[Computed]
    public function entry(): ?TimeEntry
    {
        return app(TimeTracker::class)->running(Auth::user());
    }

    public function stop(): void
    {
        app(TimeTracker::class)->stop(Auth::user());
        $this->dispatch('timer-changed');
    }
}
