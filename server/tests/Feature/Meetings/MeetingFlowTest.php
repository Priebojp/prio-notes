<?php

use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Livewire\Meetings\Create;
use App\Livewire\Meetings\Index;
use App\Livewire\Meetings\Show;
use App\Meetings\ActionItemDraft;
use App\Meetings\Contracts\Summarizer;
use App\Meetings\Contracts\Transcriber;
use App\Meetings\MeetingNotes;
use App\Meetings\MissingCredentials;
use App\Meetings\Transcript;
use App\Meetings\TranscriptSegment;
use App\Models\Company;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config(['prio.capacity' => [1 => 1, 2 => 3]]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->app->instance(Transcriber::class, new class implements Transcriber
    {
        public function transcribe(string $disk, string $path, ?string $language): Transcript
        {
            return new Transcript([
                new TranscriptSegment('speaker_0', 0, 3, 'Treba poslať ponuku do piatku.'),
                new TranscriptSegment('speaker_1', 3, 6, 'Dobre, ja ju pošlem.'),
            ], 'slk', 6);
        }
    });

    $this->app->instance(Summarizer::class, new class implements Summarizer
    {
        public function summarize(Meeting $meeting): MeetingNotes
        {
            return new MeetingNotes('Ponuka pre klienta', 'Dohodli sme ponuku.', ['Ponuka do piatku.'], [], [
                new ActionItemDraft('Poslať ponuku', 'Klient čaká.', 'Jano', '2026-11-06', Priority::P1),
                new ActionItemDraft('Overiť ceny', null, null, null, Priority::P3),
            ]);
        }
    });
});

function recordMeeting(array $overrides = []): Meeting
{
    Livewire::test(Create::class)
        ->set('audio', UploadedFile::fake()->create('porada.webm', 200, 'audio/webm'))
        ->set('consent', true)
        ->set($overrides)
        ->call('save')
        ->assertHasNoErrors();

    return Meeting::latest('id')->firstOrFail();
}

it('stores the recording and runs transcription and minutes', function () {
    $meeting = recordMeeting();

    expect($meeting->status)->toBe(MeetingStatus::Ready)
        ->and($meeting->title)->toBe('Ponuka pre klienta')
        ->and($meeting->speakers)->toBe(['speaker_0' => 'Rečník 1', 'speaker_1' => 'Rečník 2'])
        ->and($meeting->segments())->toHaveCount(2)
        ->and($meeting->duration_seconds)->toBe(6)
        ->and($meeting->actionItems()->count())->toBe(2);

    Storage::disk('local')->assertExists($meeting->audio_path);
});

it('keeps a title the user typed', function () {
    expect(recordMeeting(['title' => 'Týždenný sync'])->title)->toBe('Týždenný sync');
});

it('requires consent and a recording', function () {
    Livewire::test(Create::class)->call('save')->assertHasErrors(['audio', 'consent']);
});

it('rejects files that are not audio', function () {
    Livewire::test(Create::class)
        ->set('audio', UploadedFile::fake()->create('tabulka.pdf', 10, 'application/pdf'))
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors('audio');
});

it('marks the meeting failed and can retry from the failed step', function () {
    $this->app->instance(Summarizer::class, new class implements Summarizer
    {
        public function summarize(Meeting $meeting): MeetingNotes
        {
            throw new RuntimeException('API nedostupné');
        }
    });

    $meeting = null;
    try {
        $meeting = recordMeeting();
    } catch (RuntimeException) {
        $meeting = Meeting::latest('id')->firstOrFail();
    }

    // Sync queue rethrows after the job's failed() hook ran.
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Failed)
        ->and($meeting->fresh()->error)->toContain('API nedostupné')
        ->and($meeting->fresh()->transcript)->not->toBeNull();
});

it('turns a suggestion into a task in the chosen priority', function () {
    $company = Company::factory()->for($this->user)->create();
    $project = Project::factory()->for($this->user)->for($company)->create();
    $meeting = recordMeeting(['projectId' => $project->id]);
    $item = $meeting->actionItems()->where('title', 'Poslať ponuku')->firstOrFail();

    Livewire::test(Show::class, ['meeting' => $meeting])
        ->set("items.{$item->id}.title", 'Poslať ponuku klientovi')
        ->call('accept', $item->id)
        ->assertHasNoErrors();

    $task = Task::where('title', 'Poslať ponuku klientovi')->firstOrFail();

    expect($task->priority)->toBe(Priority::P1)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->meeting_id)->toBe($meeting->id)
        ->and($task->due_date->toDateString())->toBe('2026-11-06')
        ->and($task->notes)->toContain('Klient čaká.')->toContain('Zodpovedný: Jano')
        ->and($item->fresh()->status)->toBe(ActionItemStatus::Accepted)
        ->and($item->fresh()->task_id)->toBe($task->id);
});

it('asks what to demote when a suggestion goes into a full P1', function () {
    Task::factory()->for($this->user)->priority(Priority::P1)->create(['title' => 'Staré P1']);
    $meeting = recordMeeting();
    $item = $meeting->actionItems()->where('title', 'Poslať ponuku')->firstOrFail();

    Livewire::test(Show::class, ['meeting' => $meeting])
        ->call('accept', $item->id)
        ->assertSet('conflictPriority', 1)
        ->assertSee('Staré P1');

    // The task exists (waiting in backlog) until the user picks what to demote.
    expect(Task::where('title', 'Poslať ponuku')->first()->priority)->toBe(Priority::Backlog);
});

it('creates all suggestions at once and can dismiss and restore', function () {
    $meeting = recordMeeting();
    [$first, $second] = $meeting->actionItems()->orderBy('id')->get();

    $page = Livewire::test(Show::class, ['meeting' => $meeting])
        ->call('dismiss', $first->id)
        ->call('restore', $first->id)
        ->set("items.{$first->id}.priority", 2)
        ->call('acceptAll');

    expect($meeting->tasks()->pluck('priority', 'title')->map->value->all())
        ->toEqual(['Poslať ponuku' => 2, 'Overiť ceny' => 3]);
    $page->assertSet('items', []);
});

it('renames speakers and re-runs the minutes', function () {
    $meeting = recordMeeting();
    $meeting->actionItems()->first()->update(['status' => ActionItemStatus::Accepted]);

    Livewire::test(Show::class, ['meeting' => $meeting])
        ->set('speakerNames.speaker_0', 'Peter')
        ->set('speakerNames.speaker_1', 'Jano')
        ->call('saveSpeakers');

    expect($meeting->fresh()->speakers)->toBe(['speaker_0' => 'Peter', 'speaker_1' => 'Jano'])
        // accepted suggestion kept, pending ones replaced by the new run
        ->and($meeting->actionItems()->count())->toBe(3);
});

it('shows meetings and their pages only to the owner', function () {
    $meeting = recordMeeting();

    $this->get(route('meetings.index'))->assertOk()->assertSee('Ponuka pre klienta');
    $this->get(route('meetings.show', $meeting))->assertOk()->assertSee('Dohodli sme ponuku.')->assertSee('Treba poslať ponuku do piatku.');
    $this->get(route('meetings.audio', $meeting))->assertOk();
    $this->get(route('meetings.create'))->assertOk();
    Livewire::test(Index::class)->assertSee('2 návrhy úloh');

    $this->actingAs(User::factory()->create());
    $this->get(route('meetings.show', $meeting))->assertNotFound();
    $this->get(route('meetings.audio', $meeting))->assertNotFound();
});

it('deletes a meeting with its recording but keeps created tasks', function () {
    $meeting = recordMeeting();
    $item = $meeting->actionItems()->where('title', 'Overiť ceny')->firstOrFail();
    $page = Livewire::test(Show::class, ['meeting' => $meeting]);
    $page->call('accept', $item->id);

    $page->call('delete')->assertRedirect(route('meetings.index'));

    Storage::disk('local')->assertMissing($meeting->audio_path);
    expect(Meeting::count())->toBe(0)
        ->and(Task::where('title', 'Overiť ceny')->first()->meeting_id)->toBeNull();
});

it('prunes old recordings but keeps the transcript', function () {
    $old = recordMeeting();
    $old->forceFill(['processed_at' => now()->subDays(31)])->save();
    $fresh = recordMeeting();

    $this->artisan('meetings:prune-audio')->assertSuccessful();

    Storage::disk('local')->assertMissing($old->audio_path);
    Storage::disk('local')->assertExists($fresh->audio_path);
    expect($old->fresh()->audio_path)->toBeNull()
        ->and($old->fresh()->audio_deleted_at)->not->toBeNull()
        ->and($old->fresh()->transcript)->not->toBeNull();
});

it('fails right away when an API key is missing', function () {
    $this->app->instance(Transcriber::class, new class implements Transcriber
    {
        public function transcribe(string $disk, string $path, ?string $language): Transcript
        {
            throw new MissingCredentials('Chýba ELEVENLABS_API_KEY v .env.');
        }
    });

    $meeting = recordMeeting();

    expect($meeting->status)->toBe(MeetingStatus::Failed)
        ->and($meeting->error)->toBe('Prepis zlyhal: Chýba ELEVENLABS_API_KEY v .env.');
});
