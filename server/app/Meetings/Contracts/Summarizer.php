<?php

namespace App\Meetings\Contracts;

use App\Meetings\MeetingNotes;
use App\Models\Meeting;

interface Summarizer
{
    public function summarize(Meeting $meeting): MeetingNotes;
}
