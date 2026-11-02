<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\Task;
use App\Services\TimeTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimerController extends Controller
{
    public function __construct(private readonly TimeTracker $tracker) {}

    public function show(Request $request): JsonResponse
    {
        $running = $this->tracker->running($request->user());

        return response()->json(['running' => $running ? new TimeEntryResource($running) : null]);
    }

    public function start(Request $request, Task $task): TimeEntryResource
    {
        abort_unless($task->user_id === $request->user()->id, 404);

        return new TimeEntryResource($this->tracker->start($task)->load('task'));
    }

    public function stop(Request $request): JsonResponse
    {
        $entry = $this->tracker->stop($request->user());

        return response()->json(['stopped' => $entry ? new TimeEntryResource($entry->load('task')) : null]);
    }
}
