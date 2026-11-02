<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Meeting> $meetings
 */
#[Title('Porady')]
class Index extends Component
{
    use WithPagination;

    /** @return LengthAwarePaginator<int, Meeting> */
    #[Computed]
    public function meetings(): LengthAwarePaginator
    {
        return Auth::user()->meetings()
            ->with('project.company')
            ->withCount(['actionItems as pending_items_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest('held_at')
            ->paginate(20);
    }

    #[Computed]
    public function anyProcessing(): bool
    {
        return collect($this->meetings->items())->contains(fn (Meeting $m) => $m->status->isProcessing());
    }

    public function render(): View
    {
        return view('livewire.meetings.index');
    }
}
