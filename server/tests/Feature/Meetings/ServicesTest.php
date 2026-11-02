<?php

use Anthropic\Client;
use App\Enums\Priority;
use App\Meetings\ClaudeSummarizer;
use App\Meetings\ElevenLabsTranscriber;
use App\Models\Meeting;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('sends the recording to ElevenLabs and groups words into speaker turns', function () {
    Storage::fake('local');
    Storage::disk('local')->put('meetings/1/a.webm', 'audio-bytes');

    Http::fake([ElevenLabsTranscriber::ENDPOINT => Http::response([
        'language_code' => 'slk',
        'audio_duration_secs' => 12.4,
        'words' => [
            ['text' => 'Ahoj', 'type' => 'word', 'start' => 0.1, 'end' => 0.4, 'speaker_id' => 'speaker_0'],
            ['text' => ' ', 'type' => 'spacing', 'start' => 0.4, 'end' => 0.5, 'speaker_id' => 'speaker_0'],
            ['text' => 'Jano.', 'type' => 'word', 'start' => 0.5, 'end' => 0.9, 'speaker_id' => 'speaker_0'],
            ['text' => ' ', 'type' => 'spacing', 'start' => 0.9, 'end' => 1.0, 'speaker_id' => 'speaker_0'],
            ['text' => '(smiech)', 'type' => 'audio_event', 'start' => 1.0, 'end' => 1.5, 'speaker_id' => 'speaker_1'],
            ['text' => 'Čau.', 'type' => 'word', 'start' => 1.6, 'end' => 2.0, 'speaker_id' => 'speaker_1'],
            ['text' => ' ', 'type' => 'spacing', 'start' => 2.0, 'end' => 2.1, 'speaker_id' => 'speaker_1'],
            ['text' => 'Poďme.', 'type' => 'word', 'start' => 2.2, 'end' => 2.6, 'speaker_id' => 'speaker_0'],
        ],
    ])]);

    $transcript = (new ElevenLabsTranscriber('el-key', 'scribe_v2'))->transcribe('local', 'meetings/1/a.webm', 'sk');

    expect(array_map(fn ($s) => [$s->speaker, $s->text, $s->start], $transcript->segments))->toBe([
        ['speaker_0', 'Ahoj Jano.', 0.1],
        ['speaker_1', 'Čau.', 1.6],
        ['speaker_0', 'Poďme.', 2.2],
    ])
        ->and($transcript->durationSeconds)->toBe(12)
        ->and($transcript->speakers())->toBe(['speaker_0', 'speaker_1']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('xi-api-key', 'el-key')
        && collect($request->data())->contains(fn ($part) => $part['name'] === 'language_code' && $part['contents'] === 'sk')
        && collect($request->data())->contains(fn ($part) => $part['name'] === 'diarize' && $part['contents'] === 'true')
        && collect($request->data())->contains(fn ($part) => $part['name'] === 'file'));
});

it('omits the language for auto-detection', function () {
    Storage::fake('local');
    Storage::disk('local')->put('a.webm', 'x');
    Http::fake([ElevenLabsTranscriber::ENDPOINT => Http::response(['words' => []])]);

    (new ElevenLabsTranscriber('el-key', 'scribe_v2'))->transcribe('local', 'a.webm', null);

    Http::assertSent(fn (Request $request) => ! collect($request->data())->contains(fn ($part) => $part['name'] === 'language_code'));
});

it('fails clearly without an ElevenLabs key', function () {
    (new ElevenLabsTranscriber(null, 'scribe_v2'))->transcribe('local', 'a.webm', 'sk');
})->throws(RuntimeException::class, 'ELEVENLABS_API_KEY');

/**
 * @param  array<string, mixed>  $message
 * @param  array<int, mixed>  $history
 */
function claudeWithResponse(array $message, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode($message)),
    ]));
    $stack->push(Middleware::history($history));

    return new Client(apiKey: 'test-key', requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0]);
}

function claudeMessage(string $text, string $stopReason = 'end_turn'): array
{
    return [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => $text, 'citations' => null]],
        'stop_reason' => $stopReason, 'stop_sequence' => null,
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ];
}

it('asks Claude for structured minutes and parses them', function () {
    $this->travelTo('2026-11-04 10:00');
    $meeting = Meeting::factory()->ready()->create(['title' => 'Web', 'held_at' => '2026-11-04 09:00']);
    $history = [];
    $client = claudeWithResponse(claudeMessage(json_encode([
        'title' => 'Redizajn webu',
        'summary' => "Prebrali sme web.\n\n### Ponuka\n- do piatku",
        'decisions' => ['Ponuka pôjde do piatku.'],
        'open_questions' => [],
        'action_items' => [
            ['title' => 'Poslať ponuku', 'notes' => 'Klient čaká.', 'owner' => 'Rečník 2', 'due_date' => '2026-11-06', 'priority' => 2],
            ['title' => 'Zistiť hosting', 'notes' => null, 'owner' => null, 'due_date' => 'piatok', 'priority' => 9],
        ],
    ])), $history);

    $notes = (new ClaudeSummarizer($client, 'claude-opus-5-5', 'medium'))->summarize($meeting);

    expect($notes->title)->toBe('Redizajn webu')
        ->and($notes->decisions)->toBe(['Ponuka pôjde do piatku.'])
        ->and($notes->actionItems)->toHaveCount(2)
        ->and($notes->actionItems[0]->dueDate)->toBe('2026-11-06')
        ->and($notes->actionItems[0]->priority)->toBe(Priority::P2)
        ->and($notes->actionItems[1]->dueDate)->toBeNull()
        ->and($notes->actionItems[1]->priority)->toBe(Priority::P3);

    $request = $history[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $request->getUri())->toContain('/v1/messages')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['fallbacks'])->toBe('default')
        ->and($body['output_config']['effort'])->toBe('medium')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and($body['messages'][0]['content'])->toContain('Date: 2026-11-04 (Wednesday)')
        ->toContain('[00:04] Rečník 2: Do piatku pošlem ponuku.');
});

it('reports a refusal instead of parsing it', function () {
    $history = [];
    $client = claudeWithResponse(claudeMessage('', 'refusal'), $history);

    (new ClaudeSummarizer($client, 'claude-opus-5-5', 'medium'))->summarize(Meeting::factory()->ready()->create());
})->throws(RuntimeException::class, 'odmietol');

it('fails clearly without an Anthropic key', function () {
    (new ClaudeSummarizer(null, 'claude-opus-5-5', 'medium'))->summarize(Meeting::factory()->ready()->create());
})->throws(RuntimeException::class, 'ANTHROPIC_API_KEY');
