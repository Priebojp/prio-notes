<?php

namespace App\Meetings;

final readonly class Transcript
{
    /**
     * @param  list<TranscriptSegment>  $segments
     */
    public function __construct(
        public array $segments,
        public ?string $language = null,
        public ?int $durationSeconds = null,
    ) {}

    /** @return list<string> distinct speaker ids in order of first appearance */
    public function speakers(): array
    {
        return array_values(array_unique(array_map(fn (TranscriptSegment $s) => $s->speaker, $this->segments)));
    }
}
