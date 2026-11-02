<?php

namespace App\Meetings;

use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Stores a new meeting recording and starts processing it (web and phone app). */
class MeetingRecorder
{
    public function __construct(private readonly MeetingPipeline $pipeline) {}

    /** @return list<string> validation rules for the audio file */
    public static function audioRules(): array
    {
        return ['required', 'file', 'max:'.config('meetings.max_upload_mb') * 1024, 'mimetypes:audio/*,video/webm,video/mp4,video/ogg'];
    }

    public function store(User $user, UploadedFile $audio, CarbonInterface $heldAt, ?string $title = null, ?int $projectId = null, ?string $language = null): Meeting
    {
        $meeting = $user->meetings()->create([
            'title' => filled($title) ? $title : Meeting::defaultTitle($heldAt),
            'project_id' => $projectId,
            'held_at' => $heldAt,
            'language' => $language ?: null,
        ]);

        $extension = $audio->guessExtension() ?: ($audio->getClientOriginalExtension() ?: 'bin');
        $path = $audio->storeAs('meetings/'.$user->id, $meeting->id.'-'.Str::lower(Str::random(10)).'.'.$extension, Meeting::disk());

        $meeting->forceFill([
            'audio_path' => $path,
            'audio_mime' => $audio->getMimeType(),
            'audio_size' => $audio->getSize(),
        ])->save();

        $this->pipeline->process($meeting);

        return $meeting;
    }
}
