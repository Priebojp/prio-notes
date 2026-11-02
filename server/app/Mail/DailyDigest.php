<?php

namespace App\Mail;

use App\Digest\DigestData;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DigestData $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->digest->subject());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-digest');
    }

    /** One Markdown list line for a task. */
    public static function taskLine(Task $task): string
    {
        $parts = [];

        if ($task->project) {
            $parts[] = $task->project->company->name.' · '.$task->project->name;
        }
        if ($task->due_date) {
            $parts[] = match (true) {
                $task->due_date->lt(today()) => '⚠ po termíne ('.$task->due_date->translatedFormat('j. n.').')',
                $task->due_date->isToday() => 'dnes',
                $task->due_date->isTomorrow() => 'zajtra',
                default => $task->due_date->translatedFormat('l j. n.'),
            };
        }

        $title = str_replace(['*', '_', '[', ']', '`'], ['\*', '\_', '\[', '\]', '\`'], e($task->title));

        return "- **[{$task->priority->label()}]** {$title}".($parts ? ' — '.e(implode(' · ', $parts)) : '');
    }
}
