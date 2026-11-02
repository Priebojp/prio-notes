<?php

namespace App\Meetings\Contracts;

use App\Meetings\Transcript;

interface Transcriber
{
    /**
     * @param  string|null  $language  ISO-639-1 code, null = auto-detect
     */
    public function transcribe(string $disk, string $path, ?string $language): Transcript;
}
