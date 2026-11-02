<?php

namespace App\Meetings;

/** What the summarizer extracts from a transcript. */
final readonly class MeetingNotes
{
    /**
     * @param  list<string>  $decisions
     * @param  list<string>  $openQuestions
     * @param  list<ActionItemDraft>  $actionItems
     */
    public function __construct(
        public string $title,
        public string $summary,
        public array $decisions,
        public array $openQuestions,
        public array $actionItems,
    ) {}
}
