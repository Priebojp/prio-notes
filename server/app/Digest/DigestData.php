<?php

namespace App\Digest;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Everything the daily e-mail shows, computed for one user and day. */
final readonly class DigestData
{
    /**
     * @param  Collection<int, Task>  $focus  ranked: everything overdue/due today, topped up to a few more
     * @param  Collection<int, Task>  $upcoming  due in the next days, not already in $focus
     * @param  Collection<string, int>  $trackedYesterday  seconds per "Company · Project"
     * @param  Collection<int, Meeting>  $meetingsToReview  with pending_items_count
     */
    public function __construct(
        public User $user,
        public CarbonImmutable $date,
        public Collection $focus,
        public Collection $upcoming,
        public int $overdueCount,
        public int $dueTodayCount,
        public int $p1Count,
        public ?int $p1Capacity,
        public Collection $trackedYesterday,
        public Collection $meetingsToReview,
    ) {}

    public function isEmpty(): bool
    {
        return $this->focus->isEmpty() && $this->upcoming->isEmpty() && $this->meetingsToReview->isEmpty();
    }

    public function subject(): string
    {
        $parts = array_filter([
            $this->overdueCount ? "{$this->overdueCount} po termíne" : null,
            $this->dueTodayCount ? "{$this->dueTodayCount} na dnes" : null,
            $this->p1Count ? "{$this->p1Count}× P1" : null,
        ]);

        return ucfirst($this->date->translatedFormat('l j. n.')).': '.($parts ? implode(', ', $parts) : 'tvoj plán');
    }

    public function trackedYesterdayTotal(): int
    {
        return $this->trackedYesterday->sum();
    }
}
