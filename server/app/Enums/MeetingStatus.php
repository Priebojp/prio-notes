<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Uploaded = 'uploaded';
    case Transcribing = 'transcribing';
    case Summarizing = 'summarizing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Čaká na spracovanie',
            self::Transcribing => 'Prepisuje sa',
            self::Summarizing => 'Pripravuje sa zápis',
            self::Ready => 'Hotovo',
            self::Failed => 'Chyba',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'green',
            self::Failed => 'red',
            default => 'amber',
        };
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Uploaded, self::Transcribing, self::Summarizing], true);
    }
}
