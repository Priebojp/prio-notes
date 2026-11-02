<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meetings:prune-audio')]
#[Description('Delete meeting recordings older than meetings.audio_retention_days (transcripts and minutes stay)')]
class PruneMeetingAudio extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(config('meetings.audio_retention_days'));
        $count = 0;

        Meeting::query()
            ->whereNotNull('audio_path')
            ->where('status', MeetingStatus::Ready)
            ->where('processed_at', '<', $cutoff)
            ->each(function (Meeting $meeting) use (&$count) {
                $meeting->deleteAudio();
                $count++;
            });

        $this->info("Deleted {$count} recording(s).");

        return self::SUCCESS;
    }
}
