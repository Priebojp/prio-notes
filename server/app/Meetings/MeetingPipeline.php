<?php

namespace App\Meetings;

use App\Enums\MeetingStatus;
use App\Jobs\SummarizeMeeting;
use App\Jobs\TranscribeMeeting;
use App\Models\Meeting;
use Illuminate\Support\Facades\Bus;

/** Queues the recording → transcript → minutes steps. */
class MeetingPipeline
{
    public function process(Meeting $meeting): void
    {
        $meeting->forceFill(['status' => MeetingStatus::Uploaded, 'error' => null])->save();

        Bus::chain([
            new TranscribeMeeting($meeting),
            new SummarizeMeeting($meeting),
        ])->dispatch();
    }

    /** Redo only the AI minutes, e.g. after renaming speakers. */
    public function summarize(Meeting $meeting): void
    {
        $meeting->forceFill(['status' => MeetingStatus::Summarizing, 'error' => null])->save();

        SummarizeMeeting::dispatch($meeting);
    }

    /** Continue from whatever step failed. */
    public function retry(Meeting $meeting): void
    {
        $meeting->transcript === null ? $this->process($meeting) : $this->summarize($meeting);
    }
}
