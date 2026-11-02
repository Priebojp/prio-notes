<?php

namespace App\Models;

use App\Enums\Priority;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $due_date
 * @property Priority $priority
 * @property int $position
 * @property int|null $estimate_minutes
 * @property Carbon|null $completed_at
 */
#[Fillable(['project_id', 'title', 'notes', 'due_date', 'estimate_minutes'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Priority and position are deliberately not fillable: they may only be
     * changed through App\Services\PriorityBoard, which enforces capacities.
     */
    protected $attributes = [
        'priority' => 4,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'priority' => Priority::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeDone(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_date?->lt(today());
    }
}
