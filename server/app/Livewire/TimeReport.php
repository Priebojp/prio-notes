<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Support\TimeBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read EloquentCollection<int, TimeEntry> $entries
 */
#[Title('Čas')]
class TimeReport extends Component
{
    public const PERIODS = [
        'today' => 'Dnes',
        'this_week' => 'Tento týždeň',
        'last_week' => 'Minulý týždeň',
        'this_month' => 'Tento mesiac',
        'last_month' => 'Minulý mesiac',
    ];

    #[Url]
    public string $period = 'this_week';

    #[On('timer-changed')]
    #[On('tasks-changed')]
    public function refresh(): void
    {
        unset($this->entries);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'last_week' => [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->startOfWeek(), $now->endOfWeek()],
        };
    }

    /** @return EloquentCollection<int, TimeEntry> */
    #[Computed]
    public function entries(): EloquentCollection
    {
        return Auth::user()->timeEntries()
            ->with('task.project.company')
            ->whereBetween('started_at', $this->range())
            ->orderBy('started_at')
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return $this->sum($this->entries);
    }

    /**
     * Totals grouped company → project → task, biggest first.
     *
     * @return list<TimeBucket>
     */
    #[Computed]
    public function breakdown(): array
    {
        return $this->entries
            ->groupBy(fn (TimeEntry $e) => $e->task->project->company_id ?? 0)
            ->map(function (Collection $byCompany): TimeBucket {
                $company = $byCompany->first()->task->project?->company;

                return new TimeBucket(
                    name: $company->name ?? 'Bez projektu',
                    seconds: $this->sum($byCompany),
                    color: $company->color ?? 'zinc',
                    children: $this->sorted($byCompany
                        ->groupBy(fn (TimeEntry $e) => $e->task->project_id ?? 0)
                        ->map(fn (Collection $byProject): TimeBucket => new TimeBucket(
                            name: $byProject->first()->task->project->name ?? '—',
                            seconds: $this->sum($byProject),
                            children: $this->sorted($byProject
                                ->groupBy('task_id')
                                ->map(fn (Collection $byTask): TimeBucket => new TimeBucket(
                                    name: $byTask->first()->task->title,
                                    seconds: $this->sum($byTask),
                                    taskId: $byTask->first()->task_id,
                                ))),
                        ))),
                );
            })
            ->pipe(fn (Collection $companies) => $this->sorted($companies));
    }

    /**
     * @param  Collection<array-key, TimeBucket>  $buckets
     * @return list<TimeBucket>
     */
    private function sorted(Collection $buckets): array
    {
        return array_values($buckets->sortByDesc(fn (TimeBucket $b) => $b->seconds)->all());
    }

    /** @return Collection<string, int> seconds per day (Y-m-d), oldest first */
    #[Computed]
    public function days(): Collection
    {
        return $this->entries
            ->groupBy(fn (TimeEntry $e) => $e->started_at->toDateString())
            ->map(fn (Collection $day) => $this->sum($day));
    }

    /** @param  Collection<int, TimeEntry>  $entries */
    private function sum(Collection $entries): int
    {
        return (int) $entries->sum(fn (TimeEntry $e) => $e->elapsedSeconds());
    }
}
