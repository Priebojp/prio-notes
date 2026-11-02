<?php

namespace App\Meetings;

use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use App\Enums\Priority;
use App\Meetings\Contracts\Summarizer;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Turns a meeting transcript into minutes and suggested tasks with Claude,
 * using structured outputs so the response always matches the schema.
 */
class ClaudeSummarizer implements Summarizer
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You write meeting minutes for a busy professional who recorded a work meeting with the
        participants' consent. The transcript comes from automatic speech recognition with speaker
        diarization, so expect misheard words, missing punctuation, and speaker labels that may be
        split or merged. Infer the intended meaning, but never invent facts that are not in the
        transcript.

        Write every field in the language the meeting was held in (usually Slovak or Czech). Never
        translate into English.

        Fields:
        - title: short and specific, at most about 8 words, no date.
        - summary: the minutes in Markdown. Start with a 2-4 sentence overview, then one short
          "### heading" per topic discussed with bullet points. Keep concrete facts: numbers, names,
          prices, dates. No filler, no meta commentary about the recording.
        - decisions: things that were actually agreed, one sentence each.
        - open_questions: unresolved points and things someone still has to find out.
        - action_items: concrete follow-ups that someone committed to or was clearly asked to do.
          Do not create tasks for things that were only mentioned in passing.
          - title: starts with a verb in the imperative, at most about 10 words.
          - notes: the context needed to do the task later, 1-3 sentences.
          - owner: the person's name or role as mentioned, null if unclear.
          - due_date: YYYY-MM-DD only if a deadline was said. Resolve relative expressions such as
            "do piatku" or "budúci týždeň" against the meeting date given below; otherwise null.
          - priority: 1 = urgent and blocking, 2 = important this week, 3 = normal, 4 = someday or
            just an idea. The user's priority 1 list has only three slots, so use 1 only when the
            meeting made the urgency explicit.

        If the recording contains no real discussion, return empty lists and say so briefly in the
        summary.
        PROMPT;

    public function __construct(
        private readonly ?Client $client,
        private readonly string $model,
        private readonly string $effort,
    ) {}

    public function summarize(Meeting $meeting): MeetingNotes
    {
        if ($this->client === null) {
            throw new MissingCredentials('Chýba ANTHROPIC_API_KEY v .env.');
        }

        $message = $this->client->beta->messages->create(
            model: $this->model,
            maxTokens: 16000,
            system: self::SYSTEM_PROMPT,
            messages: [['role' => 'user', 'content' => $this->prompt($meeting)]],
            outputConfig: [
                'effort' => Effort::from($this->effort),
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
            ],
            // On a policy refusal, let the API retry on its default fallback model.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Model odmietol spracovať prepis ('.($message->stopDetails->category ?? 'bez kategórie').').');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('Odpoveď modelu bola príliš dlhá a skončila predčasne.');
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                return self::notesFromArray(json_decode($block->text, true, flags: JSON_THROW_ON_ERROR));
            }
        }

        throw new RuntimeException('Model nevrátil žiadny text.');
    }

    public function prompt(Meeting $meeting): string
    {
        $project = $meeting->project ? $meeting->project->company->name.' · '.$meeting->project->name : '—';
        $speakers = implode(', ', array_values($meeting->speakers ?? []));

        $lines = array_map(
            fn (TranscriptSegment $s) => "[{$s->timestamp()}] {$meeting->speakerName($s->speaker)}: {$s->text}",
            $meeting->segments(),
        );

        return <<<TEXT
            Meeting: {$meeting->title}
            Date: {$meeting->held_at->format('Y-m-d')} ({$meeting->held_at->locale('en')->dayName})
            Project: {$project}
            Language: {$this->languageName($meeting->language)}
            Speakers: {$speakers}

            <transcript>
            {$this->joinLines($lines)}
            </transcript>
            TEXT;
    }

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'summary', 'decisions', 'open_questions', 'action_items'],
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'decisions' => $strings,
                'open_questions' => $strings,
                'action_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['title', 'notes', 'owner', 'due_date', 'priority'],
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'notes' => $nullableString,
                            'owner' => $nullableString,
                            'due_date' => $nullableString,
                            'priority' => ['type' => 'integer', 'enum' => [1, 2, 3, 4]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function notesFromArray(array $data): MeetingNotes
    {
        $strings = fn (mixed $list) => array_values(array_filter(array_map('strval', (array) $list), 'filled'));

        return new MeetingNotes(
            title: trim((string) ($data['title'] ?? '')),
            summary: trim((string) ($data['summary'] ?? '')),
            decisions: $strings($data['decisions'] ?? []),
            openQuestions: $strings($data['open_questions'] ?? []),
            actionItems: array_values(array_map(fn (array $item) => new ActionItemDraft(
                title: trim((string) $item['title']),
                notes: filled($item['notes'] ?? null) ? trim((string) $item['notes']) : null,
                owner: filled($item['owner'] ?? null) ? trim((string) $item['owner']) : null,
                dueDate: self::validDate($item['due_date'] ?? null),
                priority: Priority::tryFrom((int) ($item['priority'] ?? 3)) ?? Priority::P3,
            ), array_filter((array) ($data['action_items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)))),
        );
    }

    private static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function languageName(?string $code): string
    {
        return match ($code) {
            'sk', 'slk' => 'Slovak',
            'cs', 'ces' => 'Czech',
            default => 'detect from the transcript',
        };
    }

    /** @param  list<string>  $lines */
    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
