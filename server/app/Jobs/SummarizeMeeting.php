<?php

namespace App\Jobs;

use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Meetings\Contracts\Summarizer;
use App\Meetings\MissingCredentials;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SummarizeMeeting implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public Meeting $meeting) {}

    public function handle(Summarizer $summarizer): void
    {
        $meeting = $this->meeting;
        $meeting->forceFill(['status' => MeetingStatus::Summarizing, 'error' => null])->save();

        try {
            $notes = $summarizer->summarize($meeting);
        } catch (MissingCredentials $e) {
            $this->fail($e);

            return;
        }

        DB::transaction(function () use ($meeting, $notes) {
            $meeting->forceFill([
                'title' => $meeting->hasDefaultTitle() && $notes->title !== '' ? $notes->title : $meeting->title,
                'summary' => $notes->summary,
                'decisions' => $notes->decisions,
                'open_questions' => $notes->openQuestions,
                'status' => MeetingStatus::Ready,
                'processed_at' => now(),
            ])->save();

            // Re-running replaces suggestions the user has not acted on yet.
            $meeting->actionItems()->where('status', ActionItemStatus::Pending)->delete();

            foreach ($notes->actionItems as $draft) {
                $meeting->actionItems()->create([
                    'title' => $draft->title,
                    'notes' => $draft->notes,
                    'owner' => $draft->owner,
                    'due_date' => $draft->dueDate,
                    'priority' => $draft->priority,
                ]);
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $this->meeting->markFailed('Zápis zlyhal: '.$e->getMessage());
    }
}
