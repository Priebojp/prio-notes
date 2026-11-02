<?php

return [

    // Disk for meeting recordings. Keep it private (local or s3).
    'disk' => env('MEETINGS_DISK', 'local'),

    // Recordings are deleted this many days after processing; transcript and
    // minutes stay. Run by the meetings:prune-audio scheduled command.
    'audio_retention_days' => (int) env('MEETINGS_AUDIO_RETENTION_DAYS', 30),

    'max_upload_mb' => (int) env('MEETINGS_MAX_UPLOAD_MB', 300),

    // Transcription language choices; '' lets the service auto-detect.
    'languages' => [
        'sk' => 'Slovenčina',
        'cs' => 'Čeština',
        '' => 'Automaticky',
    ],

];
