<?php

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Meetings\Contracts\Transcriber;
use App\Meetings\MissingCredentials;
use App\Meetings\TranscriptSegment;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class TranscribeMeeting implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public Meeting $meeting) {}

    public function handle(Transcriber $transcriber): void
    {
        $meeting = $this->meeting;

        if ($meeting->audio_path === null) {
            throw new RuntimeException('Porada nemá nahrávku.');
        }

        $meeting->forceFill(['status' => MeetingStatus::Transcribing, 'error' => null])->save();

        try {
            $transcript = $transcriber->transcribe(Meeting::disk(), $meeting->audio_path, $meeting->language);
        } catch (MissingCredentials $e) {
            $this->fail($e);

            return;
        }

        // Keep names the user already gave; number the rest in order of appearance.
        $speakers = [];
        foreach ($transcript->speakers() as $i => $id) {
            $speakers[$id] = $meeting->speakers[$id] ?? 'Rečník '.($i + 1);
        }

        $meeting->forceFill([
            'transcript' => array_map(fn (TranscriptSegment $s) => $s->toArray(), $transcript->segments),
            'speakers' => $speakers,
            'duration_seconds' => $transcript->durationSeconds,
            'language' => $meeting->language ?? $transcript->language,
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        $this->meeting->markFailed('Prepis zlyhal: '.$e->getMessage());
    }
}
