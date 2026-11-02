<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Streams a meeting recording to its owner (with Range support for seeking). */
class MeetingAudioController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): Response
    {
        abort_unless($meeting->user_id === $request->user()->id && $meeting->hasAudio(), 404);

        $disk = Storage::disk(Meeting::disk());

        // Local files are served directly (with Range support); cloud disks via a short-lived URL.
        if (config('filesystems.disks.'.Meeting::disk().'.driver') === 'local') {
            return response()->file($disk->path($meeting->audio_path), [
                'Content-Type' => $meeting->audio_mime ?? 'application/octet-stream',
            ]);
        }

        return redirect()->away($disk->temporaryUrl($meeting->audio_path, now()->addMinutes(30)));
    }
}
