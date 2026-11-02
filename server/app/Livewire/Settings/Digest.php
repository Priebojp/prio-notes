<?php

namespace App\Livewire\Settings;

use App\Digest\DigestBuilder;
use App\Mail\DailyDigest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Denný e-mail')]
class Digest extends Component
{
    public bool $enabled = true;

    public string $time = '07:00';

    public bool $weekends = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->enabled = $user->digest_enabled;
        $this->time = $user->digest_time;
        $this->weekends = $user->digest_weekends;
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekends' => ['boolean'],
        ], ['time.regex' => 'Zadaj čas vo formáte HH:MM.']);

        Auth::user()->update([
            'digest_enabled' => $this->enabled,
            'digest_time' => $this->time,
            'digest_weekends' => $this->weekends,
        ]);

        Flux::toast(variant: 'success', text: 'Nastavenia uložené.');
    }

    public function sendTest(DigestBuilder $builder): void
    {
        $user = Auth::user();
        Mail::to($user)->send(new DailyDigest($builder->build($user)));

        Flux::toast(text: "Skúšobný e-mail odoslaný na {$user->email}.");
    }
}
