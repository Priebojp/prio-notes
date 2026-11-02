<?php

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Meeting */
class MeetingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'held_at' => $this->held_at->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error' => $this->error,
            'duration_seconds' => $this->duration_seconds,
            'pending_items_count' => $this->resource->getAttribute('pending_items_count'),
            'summary' => $this->when($request->routeIs('api.meetings.show'), $this->summary),
            'url' => route('meetings.show', $this->resource),
        ];
    }
}
