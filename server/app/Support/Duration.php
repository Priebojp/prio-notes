<?php

namespace App\Support;

class Duration
{
    /** 45 min, 1 h 05 min */
    public static function human(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);
        $hours = intdiv($minutes, 60);

        return $hours > 0
            ? sprintf('%d h %02d min', $hours, $minutes % 60)
            : $minutes.' min';
    }
}
