<?php

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\PriorityBoard;
use App\Services\TimeTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Task actions for the phone app. Placing a task into a full priority column
 * answers 409 with the occupants (see bootstrap/app.php); the app then retries
 * the place call with demote_id.
 */
class TaskController extends Controller
{
    public function __construct(private readonly PriorityBoard $board) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $task = $this->board->createInBacklog($request->user(), [
            'title' => $data['title'],
            'project_id' => $data['project_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->board->place($task, Priority::from((int) $data['priority']));

        return (new TaskResource($task->load('project.company')))->response()->setStatusCode(201);
    }

    public function place(Request $request, Task $task): TaskResource
    {
        $this->authorizeOwner($request, $task);

        $data = $request->validate([
            'priority' => ['required', Rule::enum(Priority::class)],
            'index' => ['nullable', 'integer', 'min:0'],
            'demote_id' => ['nullable', 'integer'],
        ]);

        $demote = isset($data['demote_id']) ? $request->user()->tasks()->whereKey($data['demote_id'])->firstOrFail() : null;

        $this->board->place($task, Priority::from((int) $data['priority']), $data['index'] ?? null, $demote);

        return new TaskResource($task->fresh()->load('project.company'));
    }

    public function complete(Request $request, Task $task, TimeTracker $tracker): TaskResource
    {
        $this->authorizeOwner($request, $task);

        $tracker->stopTask($task);
        $this->board->complete($task);

        return new TaskResource($task->load('project.company'));
    }

    public function reopen(Request $request, Task $task): TaskResource
    {
        $this->authorizeOwner($request, $task);

        $this->board->reopen($task);

        return new TaskResource($task->fresh()->load('project.company'));
    }

    private function authorizeOwner(Request $request, Task $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 404);
    }
}
