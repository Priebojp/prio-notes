<?php

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TimeEntryResource;
use App\Models\Task;
use App\Services\TimeTracker;
use App\Services\UrgencyRanker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The phone's main screen and home-screen widget: what is most urgent right now. */
class NowController extends Controller
{
    public function __invoke(Request $request, UrgencyRanker $ranker, TimeTracker $tracker): JsonResponse
    {
        $limit = min(30, max(1, (int) $request->query('limit', 15)));
        $tasks = $ranker->top($request->user(), $limit);
        $running = $tracker->running($request->user());

        return response()->json([
            'tasks' => TaskResource::collection($tasks),
            'tiers' => $tasks->mapWithKeys(fn (Task $t) => [$t->id => $ranker->tier($t)]),
            'running' => $running ? new TimeEntryResource($running) : null,
            'capacity' => collect(Priority::cases())->mapWithKeys(fn (Priority $p) => [$p->value => [
                'label' => $p->label(),
                'open' => $request->user()->tasks()->open()->where('priority', $p)->count(),
                'max' => $p->capacity(),
            ]]),
        ]);
    }
}
