<?php

use App\Enums\Priority;
use App\Livewire\Board;
use App\Livewire\Now;
use App\Livewire\Projects;
use App\Livewire\TaskEditor;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['prio.capacity' => [1 => 1, 2 => 2]]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the main pages', function () {
    $project = Project::factory()->for($this->user)->create(['company_id' => Company::factory()->for($this->user)]);
    Task::factory()->for($this->user)->for($project)->priority(Priority::P1)->create(['title' => 'Zavolať klientovi']);

    $this->get(route('dashboard'))->assertOk()->assertSee('Zavolať klientovi');
    $this->get(route('board'))->assertOk()->assertSee('Zavolať klientovi');
    $this->get(route('projects.index'))->assertOk()->assertSee($project->name);
    $this->get(route('projects.show', $project))->assertOk()->assertSee('Zavolať klientovi');
});

it('hides other users projects', function () {
    $foreign = Project::factory()->create();

    $this->get(route('projects.show', $foreign))->assertNotFound();
});

it('quick-adds a task', function () {
    Livewire::test(Now::class)
        ->set('quickTitle', 'Poslať faktúru')
        ->set('quickPriority', 2)
        ->call('quickAdd')
        ->assertHasNoErrors();

    expect($this->user->tasks()->first())
        ->title->toBe('Poslať faktúru')
        ->priority->toBe(Priority::P2);
});

it('asks what to demote when quick-adding into a full P1', function () {
    $old = Task::factory()->for($this->user)->priority(Priority::P1)->create(['title' => 'Staré P1']);

    $component = Livewire::test(Now::class)
        ->set('quickTitle', 'Nové P1')
        ->set('quickPriority', 1)
        ->call('quickAdd')
        ->assertSet('conflictPriority', 1)
        ->assertSee('Staré P1');

    $component->set('demoteId', $old->id)->call('resolveConflict')->assertHasNoErrors();

    expect($this->user->tasks()->where('title', 'Nové P1')->first()->priority)->toBe(Priority::P1)
        ->and($old->fresh()->priority)->toBe(Priority::P2);
});

it('requires a choice to resolve a conflict', function () {
    Task::factory()->for($this->user)->priority(Priority::P1)->create();

    Livewire::test(Now::class)
        ->set('quickTitle', 'Nové P1')
        ->set('quickPriority', 1)
        ->call('quickAdd')
        ->call('resolveConflict')
        ->assertHasErrors('demoteId');
});

it('saves a task from the editor', function () {
    $project = Project::factory()->for($this->user)->create(['company_id' => Company::factory()->for($this->user)]);

    Livewire::test(TaskEditor::class)
        ->call('open', projectId: $project->id)
        ->set('title', 'Pripraviť ponuku')
        ->set('dueDate', '2026-11-10')
        ->set('priority', 3)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->tasks()->first())
        ->project_id->toBe($project->id)
        ->due_date->toDateString()->toBe('2026-11-10');
});

it('rejects another users project in the editor', function () {
    Livewire::test(TaskEditor::class)
        ->set('title', 'X')
        ->set('projectId', Project::factory()->create()->id)
        ->call('save')
        ->assertHasErrors('projectId');
});

it('cannot touch another users task', function () {
    $foreign = Task::factory()->create();

    Livewire::test(Board::class)->call('complete', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->completed_at)->toBeNull();
});

it('moves a card between columns by drag and drop', function () {
    $task = Task::factory()->for($this->user)->priority(Priority::P3)->create();

    Livewire::test(Board::class)->call('sort', $task->id, 0, '2');

    expect($task->fresh()->priority)->toBe(Priority::P2);
});

it('creates companies and projects', function () {
    Livewire::test(Projects\Index::class)
        ->call('editCompany')
        ->set('companyName', 'Exteco')
        ->call('saveCompany')
        ->assertHasNoErrors();

    $company = $this->user->companies()->first();

    Livewire::test(Projects\Index::class)
        ->call('newProject', $company->id)
        ->set('projectName', 'Web')
        ->call('saveProject')
        ->assertRedirect(route('projects.show', $this->user->projects()->first()));
});
