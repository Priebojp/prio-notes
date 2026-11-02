<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeetingResource;
use App\Meetings\MeetingRecorder;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return MeetingResource::collection($request->user()->meetings()
            ->withCount(['actionItems as pending_items_count' => fn ($q) => $q->where('status', ActionItemStatus::Pending)])
            ->latest('held_at')
            ->limit(20)
            ->get());
    }

    public function store(Request $request, MeetingRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'audio' => MeetingRecorder::audioRules(),
            'title' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'language' => ['nullable', Rule::in(array_keys(config('meetings.languages')))],
            'held_at' => ['nullable', 'date'],
            'consent' => ['accepted'],
        ]);

        $meeting = $recorder->store(
            $request->user(),
            $request->file('audio'),
            isset($data['held_at']) ? Carbon::parse($data['held_at']) : now(),
            $data['title'] ?? null,
            isset($data['project_id']) ? (int) $data['project_id'] : null,
            $data['language'] ?? 'sk',
        );

        return (new MeetingResource($meeting->fresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, Meeting $meeting): MeetingResource
    {
        abort_unless($meeting->user_id === $request->user()->id, 404);

        return new MeetingResource($meeting);
    }
}
