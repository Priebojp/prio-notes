<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
class TimeEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_title' => $this->task->title,
            'started_at' => $this->started_at->toIso8601String(),
            'elapsed_seconds' => $this->elapsedSeconds(),
            'running' => $this->isRunning(),
        ];
    }
}
