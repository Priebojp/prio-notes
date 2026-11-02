<?php

use App\Livewire\Login;
use App\Livewire\Now;
use App\Livewire\Record;
use App\Services\Account;
use App\Services\ApiConflict;
use App\Services\PrioApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const SERVER = 'http://prio.test';

beforeEach(fn () => config(['services.prio.url' => SERVER.'/']));

function signIn(): void
{
    app(Account::class)->signIn('tok-123', 'Peter');
}

function nowPayload(array $tasks = [], ?array $running = null): array
{
    return [
        'tasks' => $tasks,
        'tiers' => [],
        'running' => $running,
        'capacity' => ['1' => ['label' => 'P1', 'open' => 1, 'max' => 3]],
    ];
}

function taskPayload(int $id, string $title, int $priority = 1): array
{
    return ['id' => $id, 'title' => $title, 'priority' => $priority, 'priority_label' => 'P'.$priority, 'due_date' => null, 'done' => false, 'tracked_seconds' => 0, 'project' => ['id' => 1, 'name' => 'Web', 'company' => 'Exteco']];
}

it('keeps the token and server and forgets them on sign-out', function () {
    signIn();
    $account = app(Account::class);

    expect($account->token())->toBe('tok-123')
        ->and($account->serverUrl())->toBe(SERVER)
        ->and($account->isSignedIn())->toBeTrue();

    $account->signOut();
    expect($account->isSignedIn())->toBeFalse();
});

it('redirects to login when signed out', function () {
    $this->get('/')->assertRedirect('/login');
});

it('logs in against the server, asking for a 2FA code when needed', function () {
    Http::fake([
        SERVER.'/api/tokens' => Http::sequence()
            ->push(['two_factor' => true, 'message' => 'Kód'], 422)
            ->push(['token' => 'tok-9', 'user' => ['name' => 'Peter', 'email' => 'p@x.sk']], 201),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'p@x.sk')
        ->set('password', 'secret')
        ->call('login')
        ->assertSet('needsCode', true)
        ->set('code', '123456')
        ->call('login')
        ->assertRedirect('/');

    expect(app(Account::class)->token())->toBe('tok-9');
    Http::assertSent(fn (Request $r) => ($r['code'] ?? null) === '123456' && $r['device_name'] === 'Prio Notes Android');
});

it('shows the urgent list and sends the bearer token', function () {
    signIn();
    Http::fake([SERVER.'/api/now*' => Http::response(nowPayload([taskPayload(5, 'Poslať ponuku')]))]);

    Livewire::test(Now::class)->assertSee('Poslať ponuku')->assertSee('Exteco · Web');

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer tok-123'));
});

it('opens the demote sheet on a full priority and resolves it', function () {
    signIn();
    Http::fake([
        SERVER.'/api/now*' => Http::response(nowPayload()),
        SERVER.'/api/tasks' => Http::response(['message' => 'P1 je plná.', 'task_id' => 9, 'priority' => 1, 'index' => null, 'occupants' => [taskPayload(3, 'Staré P1')]], 409),
        SERVER.'/api/tasks/9/place' => Http::response(['data' => taskPayload(9, 'Nové')]),
    ]);

    Livewire::test(Now::class)
        ->set('quickTitle', 'Nové')
        ->set('quickPriority', 1)
        ->call('addTask')
        ->assertSee('P1 je plná.')
        ->assertSee('Staré P1')
        ->set('demoteId', 3)
        ->call('resolveConflict')
        ->assertSet('conflict', null);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/tasks/9/place') && $r['demote_id'] === 3 && $r['priority'] === 1);
});

it('signs out when the server rejects the token', function () {
    signIn();
    Http::fake([SERVER.'/api/now*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    Livewire::test(Now::class)->assertRedirect('/login');
    expect(app(Account::class)->isSignedIn())->toBeFalse();
});

it('uploads a finished recording with consent', function () {
    signIn();
    Http::fake([
        SERVER.'/api/projects' => Http::response(['data' => []]),
        SERVER.'/api/meetings' => Http::sequence()
            ->push(['data' => []])
            ->push(['data' => ['id' => 4, 'url' => SERVER.'/meetings/4']], 201)
            ->push(['data' => []]),
    ]);
    $file = tempnam(sys_get_temp_dir(), 'rec').'.m4a';
    file_put_contents($file, 'audio');

    Livewire::test(Record::class)
        ->call('recorded', $file, 'audio/m4a', 'meeting')
        ->assertSet('state', 'recorded')
        ->call('upload')
        ->assertHasErrors('consent')
        ->set('consent', true)
        ->set('language', 'cs')
        ->call('upload')
        ->assertSet('state', 'done')
        ->assertSet('doneUrl', SERVER.'/meetings/4');

    expect(is_file($file))->toBeFalse();
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/meetings') && $r->isMultipart());
});

it('turns a 409 into an ApiConflict', function () {
    signIn();
    Http::fake([SERVER.'/api/tasks/1/place' => Http::response(['message' => 'P1 je plná.', 'occupants' => []], 409)]);

    app(PrioApi::class)->place(1, 1);
})->throws(ApiConflict::class, 'P1 je plná.');
