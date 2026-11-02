<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Meetings\TranscriptSegment;
use Carbon\CarbonInterface;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $title
 * @property CarbonInterface $held_at
 * @property string|null $language
 * @property MeetingStatus $status
 * @property string|null $error
 * @property string|null $audio_path
 * @property string|null $audio_mime
 * @property int|null $audio_size
 * @property CarbonInterface|null $audio_deleted_at
 * @property int|null $duration_seconds
 * @property list<array{speaker: string, start: float, end: float, text: string}>|null $transcript
 * @property array<string, string>|null $speakers
 * @property string|null $summary
 * @property list<string>|null $decisions
 * @property list<string>|null $open_questions
 * @property CarbonInterface|null $processed_at
 */
#[Fillable(['project_id', 'title', 'held_at', 'language'])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'uploaded',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'status' => MeetingStatus::class,
            'audio_deleted_at' => 'datetime',
            'transcript' => 'array',
            'speakers' => 'array',
            'decisions' => 'array',
            'open_questions' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<MeetingActionItem, $this> */
    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public static function defaultTitle(CarbonInterface $heldAt): string
    {
        return 'Porada '.$heldAt->translatedFormat('j. n. Y');
    }

    /** True until the user or the AI gives the meeting a real title. */
    public function hasDefaultTitle(): bool
    {
        return $this->title === self::defaultTitle($this->held_at);
    }

    public static function disk(): string
    {
        return config('meetings.disk');
    }

    public function hasAudio(): bool
    {
        return $this->audio_path !== null && Storage::disk(self::disk())->exists($this->audio_path);
    }

    public function deleteAudio(): void
    {
        if ($this->audio_path !== null) {
            Storage::disk(self::disk())->delete($this->audio_path);
        }

        $this->forceFill(['audio_path' => null, 'audio_deleted_at' => now()])->save();
    }

    /** @return list<TranscriptSegment> */
    public function segments(): array
    {
        return array_map(TranscriptSegment::fromArray(...), $this->transcript ?? []);
    }

    public function speakerName(string $speaker): string
    {
        return $this->speakers[$speaker] ?? $speaker;
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => MeetingStatus::Failed, 'error' => $error])->save();
    }
}
