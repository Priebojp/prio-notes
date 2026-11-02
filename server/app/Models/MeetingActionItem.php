<?php

namespace App\Models;

use App\Enums\ActionItemStatus;
use App\Enums\Priority;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task suggested by the AI from a meeting, waiting for the user to accept it.
 *
 * @property int $id
 * @property int $meeting_id
 * @property string $title
 * @property string|null $notes
 * @property string|null $owner
 * @property CarbonInterface|null $due_date
 * @property Priority $priority
 * @property ActionItemStatus $status
 * @property int|null $task_id
 */
#[Fillable(['title', 'notes', 'owner', 'due_date', 'priority', 'status'])]
class MeetingActionItem extends Model
{
    protected $attributes = [
        'priority' => 3,
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'priority' => Priority::class,
            'status' => ActionItemStatus::class,
        ];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
