<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at
 * @property int|null $seconds
 * @property string|null $note
 */
#[Fillable(['note'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Duration so far, including a running entry. */
    public function elapsedSeconds(): int
    {
        return $this->seconds ?? (int) $this->started_at->diffInSeconds(now(), true);
    }

    public function finish(CarbonInterface $at): void
    {
        $this->forceFill([
            'ended_at' => $at,
            'seconds' => (int) $this->started_at->diffInSeconds($at, true),
        ])->save();
    }
}
