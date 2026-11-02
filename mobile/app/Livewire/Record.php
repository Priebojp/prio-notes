<?php

namespace App\Livewire;

use App\Services\Account;
use App\Services\PrioApi;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Microphone\MicrophoneCancelled;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Facades\Microphone;
use RuntimeException;

/** Record a meeting on the phone and send it to the server for transcription. */
#[Layout('layouts.app')]
class Record extends Component
{
    /** idle | recording | paused | stopping | recorded | uploading | done */
    public string $state = 'idle';

    public ?string $path = null;

    public string $title = '';

    public ?int $projectId = null;

    public string $language = 'sk';

    public bool $consent = false;

    /** @var list<array{id: int, name: string, company: string}> */
    public array $projects = [];

    /** @var list<array<string, mixed>> */
    public array $meetings = [];

    public ?string $error = null;

    public ?string $doneUrl = null;

    public function mount(): void
    {
        $this->loadLists();
    }

    public function loadLists(): void
    {
        try {
            $api = app(PrioApi::class);
            $this->projects = $api->projects();
            $this->meetings = $api->meetings();
        } catch (RuntimeException $e) {
            if (! app(Account::class)->isSignedIn()) {
                $this->redirectRoute('login', navigate: true);

                return;
            }
            $this->error = $e->getMessage();
        }
    }

    public function start(): void
    {
        $this->error = null;

        if (! Microphone::record()->id('meeting')->start()) {
            $this->error = 'Nahrávanie sa nepodarilo spustiť. Povoľ appke mikrofón.';

            return;
        }

        $this->state = 'recording';
    }

    public function pause(): void
    {
        Microphone::pause();
        $this->state = 'paused';
    }

    public function resume(): void
    {
        Microphone::resume();
        $this->state = 'recording';
    }

    public function stop(): void
    {
        $this->state = 'stopping';
        Microphone::stop();
    }

    #[OnNative(MicrophoneRecorded::class)]
    public function recorded(string $path, string $mimeType = 'audio/m4a', ?string $id = null): void
    {
        $this->path = $path;
        $this->state = 'recorded';
    }

    #[OnNative(MicrophoneCancelled::class)]
    public function cancelled(bool $cancelled = true, ?string $id = null, string $reason = ''): void
    {
        $this->state = 'idle';
        $this->error = $reason === 'permission_denied'
            ? 'Appka nemá povolený mikrofón. Povoľ ho v nastaveniach Androidu.'
            : 'Nahrávanie sa prerušilo.';
    }

    public function upload(PrioApi $api): void
    {
        $this->validate(['consent' => ['accepted']], ['consent.accepted' => 'Potvrď, že účastníci o nahrávaní vedia.']);

        if (! $this->path || ! is_file($this->path)) {
            $this->error = 'Nahrávka sa nenašla.';

            return;
        }

        $this->state = 'uploading';
        $this->error = null;

        try {
            $meeting = $api->uploadMeeting($this->path, [
                'title' => $this->title,
                'project_id' => $this->projectId,
                'language' => $this->language,
            ]);
        } catch (RuntimeException $e) {
            // Keep the file so the upload can be retried.
            $this->state = 'recorded';
            $this->error = 'Odoslanie zlyhalo: '.$e->getMessage();

            return;
        }

        @unlink($this->path);
        $this->reset('path', 'title', 'consent');
        $this->doneUrl = $meeting['url'];
        $this->state = 'done';
        $this->loadLists();
    }

    public function discard(): void
    {
        if ($this->path) {
            @unlink($this->path);
        }

        $this->reset('path', 'state', 'error', 'doneUrl');
    }

    public function render(): View
    {
        return view('livewire.record')->title('Porada');
    }
}
