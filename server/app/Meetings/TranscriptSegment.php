<?php

namespace App\Meetings;

/** One uninterrupted stretch of speech by a single speaker. */
final readonly class TranscriptSegment
{
    public function __construct(
        public string $speaker,
        public float $start,
        public float $end,
        public string $text,
    ) {}

    /** @param  array{speaker: string, start: float|int, end: float|int, text: string}  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['speaker'], (float) $data['start'], (float) $data['end'], $data['text']);
    }

    /** @return array{speaker: string, start: float, end: float, text: string} */
    public function toArray(): array
    {
        return ['speaker' => $this->speaker, 'start' => $this->start, 'end' => $this->end, 'text' => $this->text];
    }

    public function timestamp(): string
    {
        $s = (int) $this->start;

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
