<?php

namespace App\Livewire;

use App\Services\Account;
use App\Services\ApiConflict;
use App\Services\PrioApi;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/** What is most urgent right now; complete, time and quick-add tasks. */
#[Layout('layouts.app')]
class Now extends Component
{
    /** @var list<array<string, mixed>> */
    public array $tasks = [];

    /** @var array<int|string, int> task id => urgency tier */
    public array $tiers = [];

    /** @var array<string, mixed>|null */
    public ?array $running = null;

    /** @var array<int|string, array{label: string, open: int, max: int|null}> */
    public array $capacity = [];

    public string $quickTitle = '';

    public int $quickPriority = 3;

    /** @var array<string, mixed>|null 409 payload while the user picks what to demote */
    public ?array $conflict = null;

    public ?int $demoteId = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->attempt(function () {
            $data = app(PrioApi::class)->now();
            $this->tasks = $data['tasks'];
            $this->tiers = $data['tiers'];
            $this->running = $data['running'];
            $this->capacity = $data['capacity'];
            $this->error = null;
        });
    }

    public function complete(int $id): void
    {
        $this->attempt(fn () => app(PrioApi::class)->complete($id));
        $this->load();
    }

    public function toggleTimer(int $id): void
    {
        $this->attempt(fn () => ($this->running['task_id'] ?? null) === $id
            ? app(PrioApi::class)->stopTimer()
            : app(PrioApi::class)->startTimer($id));
        $this->load();
    }

    public function stopTimer(): void
    {
        $this->attempt(fn () => app(PrioApi::class)->stopTimer());
        $this->load();
    }

    public function addTask(): void
    {
        $this->validate(['quickTitle' => ['required', 'string', 'max:255']], ['quickTitle.required' => 'Napíš, čo treba spraviť.']);

        $this->attempt(fn () => app(PrioApi::class)->createTask($this->quickTitle, $this->quickPriority));
        $this->reset('quickTitle');
        $this->load();
    }

    public function resolveConflict(): void
    {
        if ($this->conflict === null || $this->demoteId === null) {
            return;
        }

        $conflict = $this->conflict;
        $this->conflict = null;

        $this->attempt(fn () => app(PrioApi::class)->place($conflict['task_id'], $conflict['priority'], $conflict['index'], $this->demoteId));
        $this->demoteId = null;
        $this->load();
    }

    public function cancelConflict(): void
    {
        $this->reset('conflict', 'demoteId');
        $this->load();
    }

    public function logout(): void
    {
        app(PrioApi::class)->logout();
        $this->redirectRoute('login', navigate: true);
    }

    public function render(Account $account): View
    {
        return view('livewire.now', ['userName' => $account->userName()])->title('Teraz');
    }

    private function attempt(callable $call): void
    {
        try {
            $call();
        } catch (ApiConflict $e) {
            $this->conflict = $e->payload;
        } catch (RuntimeException $e) {
            if (! app(Account::class)->isSignedIn()) {
                $this->redirectRoute('login', navigate: true);

                return;
            }
            $this->error = $e->getMessage();
        }
    }
}
