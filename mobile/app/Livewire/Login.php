<?php

namespace App\Livewire;

use App\Services\Account;
use App\Services\PrioApi;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $serverUrl = '';

    public string $email = '';

    public string $password = '';

    public string $code = '';

    public bool $needsCode = false;

    public ?string $error = null;

    public function mount(Account $account): void
    {
        $this->serverUrl = $account->serverUrl();
    }

    public function login(PrioApi $api, Account $account): void
    {
        $this->validate([
            'serverUrl' => ['required', 'url'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->error = null;

        try {
            $result = $api->login($this->serverUrl, $this->email, $this->password, $this->needsCode ? $this->code : null);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        if (isset($result['two_factor'])) {
            $this->needsCode = true;

            return;
        }

        $account->signIn($this->serverUrl, $result['token'], $result['user']['name']);
        $this->redirectRoute('now', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.login')->title('Prihlásenie');
    }
}
