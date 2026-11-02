<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'position' => $this->position,
            'due_date' => $this->due_date?->toDateString(),
            'done' => $this->isDone(),
            'tracked_seconds' => (int) ($this->tracked_seconds ?? 0),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'company' => $this->project->company->name,
            ] : null),
        ];
    }
}
