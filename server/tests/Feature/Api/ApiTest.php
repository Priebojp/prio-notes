<?php

use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Meetings\Contracts\Summarizer;
use App\Meetings\Contracts\Transcriber;
use App\Meetings\MeetingNotes;
use App\Meetings\Transcript;
use App\Models\Company;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TimeTracker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['prio.capacity' => [1 => 1, 2 => 2]]);
    $this->user = User::factory()->create(['email' => 'peter@example.com']);
});

it('issues a token for valid credentials and revokes it', function () {
    $response = $this->postJson('/api/tokens', ['email' => 'peter@example.com', 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertCreated()
        ->assertJsonPath('user.email', 'peter@example.com');

    $token = $response->json('token');
    $this->withToken($token)->getJson('/api/now')->assertOk();

    $this->withToken($token)->deleteJson('/api/tokens/current')->assertNoContent();
    expect($this->user->tokens()->count())->toBe(0);
});

it('rejects wrong passwords and anonymous calls', function () {
    $this->postJson('/api/tokens', ['email' => 'peter@example.com', 'password' => 'nope', 'device_name' => 'Pixel'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->getJson('/api/now')->assertUnauthorized();
});

it('requires the 2FA code when two-factor is enabled', function () {
    $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();
    $this->mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')->andReturnUsing(fn ($secret, $code) => $secret === 'SECRET' && $code === '123456');

    $login = fn (array $extra = []) => $this->postJson('/api/tokens', ['email' => 'peter@example.com', 'password' => 'password', 'device_name' => 'Pixel', ...$extra]);

    $login()->assertUnprocessable()->assertJsonPath('two_factor', true);
    $login(['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $login(['code' => '123456'])->assertCreated();
});

it('lists what is urgent now with the running timer and capacities', function () {
    Sanctum::actingAs($this->user);
    $company = Company::factory()->for($this->user)->create(['name' => 'Exteco']);
    $project = Project::factory()->for($this->user)->for($company)->create(['name' => 'Web']);
    $task = Task::factory()->for($this->user)->for($project)->priority(Priority::P1)->create(['title' => 'Ponuka']);
    Task::factory()->for($this->user)->priority(Priority::P3)->create(['title' => 'Neskôr']);
    Task::factory()->priority(Priority::P1)->create(['title' => 'Cudzia']);
    app(TimeTracker::class)->start($task);

    $this->getJson('/api/now')
        ->assertOk()
        ->assertJsonPath('tasks.0.title', 'Ponuka')
        ->assertJsonPath('tasks.0.project.company', 'Exteco')
        ->assertJsonPath('tasks.1.title', 'Neskôr')
        ->assertJsonCount(2, 'tasks')
        ->assertJsonPath('running.task_id', $task->id)
        ->assertJsonPath('capacity.1.open', 1)
        ->assertJsonPath('capacity.1.max', 1);
});

it('creates a task and answers 409 with occupants when the column is full', function () {
    Sanctum::actingAs($this->user);
    $old = Task::factory()->for($this->user)->priority(Priority::P1)->create(['title' => 'Staré P1']);

    $this->postJson('/api/tasks', ['title' => 'Do P2', 'priority' => 2])->assertCreated()->assertJsonPath('data.priority', 2);

    $conflict = $this->postJson('/api/tasks', ['title' => 'Nové P1', 'priority' => 1])
        ->assertStatus(409)
        ->assertJsonPath('priority', 1)
        ->assertJsonPath('occupants.0.title', 'Staré P1');

    $this->postJson("/api/tasks/{$conflict->json('task_id')}/place", ['priority' => 1, 'demote_id' => $old->id])
        ->assertOk()
        ->assertJsonPath('data.priority', 1);

    expect($old->fresh()->priority)->toBe(Priority::P2);
});

it('completes, reopens and times tasks, and hides other users tasks', function () {
    Sanctum::actingAs($this->user);
    $task = Task::factory()->for($this->user)->priority(Priority::P3)->create();

    $this->postJson("/api/tasks/{$task->id}/timer")->assertCreated()->assertJsonPath('data.running', true);
    $this->getJson('/api/timer')->assertJsonPath('running.task_id', $task->id);
    $this->postJson("/api/tasks/{$task->id}/complete")->assertOk()->assertJsonPath('data.done', true);
    $this->getJson('/api/timer')->assertJsonPath('running', null);
    $this->postJson("/api/tasks/{$task->id}/reopen")->assertOk()->assertJsonPath('data.done', false);
    $this->deleteJson('/api/timer')->assertOk()->assertJsonPath('stopped', null);

    $foreign = Task::factory()->create();
    $this->postJson("/api/tasks/{$foreign->id}/complete")->assertNotFound();
    $this->postJson("/api/tasks/{$foreign->id}/timer")->assertNotFound();
});

it('uploads a meeting recording from the phone', function () {
    Storage::fake('local');
    Sanctum::actingAs($this->user);
    $this->app->instance(Transcriber::class, new class implements Transcriber
    {
        public function transcribe(string $disk, string $path, ?string $language): Transcript
        {
            return new Transcript([], $language, 0);
        }
    });
    $this->app->instance(Summarizer::class, new class implements Summarizer
    {
        public function summarize(Meeting $meeting): MeetingNotes
        {
            return new MeetingNotes('Telefón', 'Nič.', [], [], []);
        }
    });

    $this->post('/api/meetings', ['audio' => UploadedFile::fake()->create('rec.m4a', 50, 'audio/mp4')], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('consent');

    $response = $this->post('/api/meetings', [
        'audio' => UploadedFile::fake()->create('rec.m4a', 50, 'audio/mp4'),
        'consent' => '1',
        'language' => 'cs',
    ], ['Accept' => 'application/json'])->assertCreated();

    $meeting = Meeting::findOrFail($response->json('data.id'));
    expect($meeting->language)->toBe('cs')
        ->and($meeting->status)->toBe(MeetingStatus::Ready);

    $this->getJson('/api/meetings')->assertOk()->assertJsonPath('data.0.id', $meeting->id);
    $this->getJson("/api/meetings/{$meeting->id}")->assertOk()->assertJsonPath('data.summary', 'Nič.');
    $this->getJson('/api/meetings/'.Meeting::factory()->create()->id)->assertNotFound();
});

it('lists active projects', function () {
    Sanctum::actingAs($this->user);
    $company = Company::factory()->for($this->user)->create(['name' => 'Exteco']);
    Project::factory()->for($this->user)->for($company)->create(['name' => 'Web']);
    Project::factory()->for($this->user)->for($company)->create(['name' => 'Starý', 'archived_at' => now()]);

    $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.company', 'Exteco');
});
