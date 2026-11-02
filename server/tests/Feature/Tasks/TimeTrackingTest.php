<?php

use App\Livewire\Now;
use App\Livewire\RunningTimer;
use App\Livewire\TaskEditor;
use App\Livewire\TimeReport;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTracker;
use App\Support\Duration;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-11-04 10:00'); // Wednesday
    $this->user = User::factory()->create();
    $this->tracker = app(TimeTracker::class);
});

it('formats durations', function () {
    expect(Duration::human(0))->toBe('0 min')
        ->and(Duration::human(45 * 60 + 30))->toBe('45 min')
        ->and(Duration::human(65 * 60))->toBe('1 h 05 min');
});

it('keeps a single running timer per user', function () {
    [$a, $b] = Task::factory()->for($this->user)->count(2)->create();

    $this->tracker->start($a);
    $this->travel(30)->minutes();
    $this->tracker->start($b);

    expect($a->timeEntries()->sole())
        ->isRunning()->toBeFalse()
        ->seconds->toBe(30 * 60)
        ->and($this->tracker->running($this->user)->task_id)->toBe($b->id);
});

it('does not restart a timer already running on the task', function () {
    $task = Task::factory()->for($this->user)->create();

    $first = $this->tracker->start($task);
    $this->travel(5)->minutes();

    expect($this->tracker->start($task)->is($first))->toBeTrue()
        ->and($task->timeEntries()->count())->toBe(1);
});

it('logs time manually', function () {
    $task = Task::factory()->for($this->user)->create();

    $entry = $this->tracker->log($task, 90, now()->subDay());

    expect($entry->seconds)->toBe(5400)
        ->and($entry->ended_at->format('Y-m-d H:i'))->toBe('2026-11-03 17:00')
        ->and($entry->started_at->format('H:i'))->toBe('15:30');
});

it('stops the timer when the task is completed', function () {
    $this->actingAs($this->user);
    $task = Task::factory()->for($this->user)->create();
    $this->tracker->start($task);
    $this->travel(10)->minutes();

    Livewire::test(Now::class)->call('complete', $task->id);

    expect($task->timeEntries()->sole()->seconds)->toBe(600);
});

it('starts and stops timers from the pages', function () {
    $this->actingAs($this->user);
    $task = Task::factory()->for($this->user)->create(['title' => 'Písať ponuku']);

    Livewire::test(Now::class)->call('startTimer', $task->id)->assertDispatched('timer-changed');
    Livewire::test(RunningTimer::class)->assertSee('Písať ponuku')->call('stop');

    expect($this->tracker->running($this->user))->toBeNull();
});

it('cannot start a timer on another users task', function () {
    $this->actingAs($this->user);

    Livewire::test(Now::class)->call('startTimer', Task::factory()->create()->id)->assertNotFound();
});

it('logs and deletes time in the task editor', function () {
    $this->actingAs($this->user);
    $task = Task::factory()->for($this->user)->create();

    $editor = Livewire::test(TaskEditor::class)
        ->call('open', id: $task->id)
        ->set('logMinutes', 25)
        ->call('logTime')
        ->assertHasNoErrors()
        ->assertSee('25 min');

    $editor->call('deleteEntry', $task->timeEntries()->sole()->id);

    expect($task->timeEntries()->count())->toBe(0);
});

it('rejects logging time in the future', function () {
    $this->actingAs($this->user);
    $task = Task::factory()->for($this->user)->create();

    Livewire::test(TaskEditor::class)
        ->call('open', id: $task->id)
        ->set('logMinutes', 10)
        ->set('logDate', '2026-11-05')
        ->call('logTime')
        ->assertHasErrors('logDate');
});

it('reports time per company, project and task for the period', function () {
    $this->actingAs($this->user);
    $company = Company::factory()->for($this->user)->create(['name' => 'Exteco']);
    $project = Project::factory()->for($this->user)->for($company)->create(['name' => 'Web']);
    $task = Task::factory()->for($this->user)->for($project)->create(['title' => 'Header']);
    $loose = Task::factory()->for($this->user)->create(['title' => 'Mail']);

    $this->tracker->log($task, 60, now()->startOfWeek());           // Monday
    $this->tracker->log($task, 30, now()->subDay());                 // Tuesday
    $this->tracker->log($loose, 15, now());
    $this->tracker->log($task, 120, now()->subWeek());               // last week
    TimeEntry::factory()->create(); // belongs to another user

    Livewire::test(TimeReport::class)
        ->assertSet('total', 105 * 60)
        ->assertSee(['Exteco', 'Web', 'Header', '1 h 30 min', 'Bez projektu', 'Mail', '15 min'])
        ->set('period', 'last_week')
        ->assertSet('total', 120 * 60);

    $this->get(route('time'))->assertOk();
});
