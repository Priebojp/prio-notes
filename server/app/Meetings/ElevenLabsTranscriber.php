<?php

namespace App\Meetings;

use App\Meetings\Contracts\Transcriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * ElevenLabs Scribe speech-to-text with speaker diarization.
 *
 * @see https://elevenlabs.io/docs/api-reference/speech-to-text/convert
 */
class ElevenLabsTranscriber implements Transcriber
{
    public const ENDPOINT = 'https://api.elevenlabs.io/v1/speech-to-text';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
    ) {}

    public function transcribe(string $disk, string $path, ?string $language): Transcript
    {
        if (blank($this->apiKey)) {
            throw new MissingCredentials('Chýba ELEVENLABS_API_KEY v .env.');
        }

        $stream = Storage::disk($disk)->readStream($path)
            ?? throw new RuntimeException("Nahrávka {$path} neexistuje.");

        $fields = array_filter([
            'model_id' => $this->model,
            'language_code' => $language,
            'diarize' => 'true',
            'tag_audio_events' => 'false',
            'timestamps_granularity' => 'word',
        ], fn (?string $value) => filled($value));

        $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
            ->connectTimeout(30)
            ->timeout(1200)
            ->attach('file', $stream, basename($path))
            ->post(self::ENDPOINT, $fields)
            ->throw();

        return $this->parse($response->json());
    }

    /**
     * Group the word-level response into speaker turns.
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(array $data): Transcript
    {
        $segments = [];
        $current = null;

        foreach ($data['words'] ?? [] as $word) {
            $type = $word['type'] ?? 'word';

            if ($type === 'audio_event') {
                continue;
            }

            if ($type === 'spacing') {
                if ($current !== null) {
                    $current['text'] .= $word['text'];
                }

                continue;
            }

            $speaker = (string) ($word['speaker_id'] ?? 'speaker_0');

            if ($current === null || $current['speaker'] !== $speaker) {
                if ($current !== null) {
                    $segments[] = $current;
                }
                $current = ['speaker' => $speaker, 'start' => (float) $word['start'], 'end' => (float) $word['end'], 'text' => ''];
            }

            $current['text'] .= $word['text'];
            $current['end'] = (float) $word['end'];
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        $segments = array_values(array_filter(
            array_map(fn (array $s) => new TranscriptSegment($s['speaker'], $s['start'], $s['end'], trim($s['text'])), $segments),
            fn (TranscriptSegment $s) => $s->text !== '',
        ));

        $duration = $data['audio_duration_secs'] ?? ($segments === [] ? null : end($segments)->end);

        return new Transcript(
            segments: $segments,
            language: $data['language_code'] ?? null,
            durationSeconds: $duration === null ? null : (int) round((float) $duration),
        );
    }
}
