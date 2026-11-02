<?php

namespace App\Livewire\Meetings;

use App\Meetings\MeetingPipeline;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Nová porada')]
class Create extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $audio = null;

    public string $title = '';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public string $language = 'sk';

    public string $heldAt = '';

    public bool $consent = false;

    public function mount(): void
    {
        $this->heldAt = now()->format('Y-m-d\TH:i');
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function projects(): Collection
    {
        return Auth::user()->projects()->active()->with('company')->get()
            ->sortBy(fn (Project $p) => $p->company->name.' '.$p->name);
    }

    public function save(MeetingPipeline $pipeline): void
    {
        $this->validate([
            'audio' => ['required', 'file', 'max:'.config('meetings.max_upload_mb') * 1024, 'mimetypes:audio/*,video/webm,video/mp4,video/ogg'],
            'title' => ['nullable', 'string', 'max:255'],
            'projectId' => ['nullable', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'language' => ['nullable', Rule::in(array_keys(config('meetings.languages')))],
            'heldAt' => ['required', 'date'],
            'consent' => ['accepted'],
        ], [
            'audio.required' => 'Nahraj poradu alebo vyber súbor.',
            'audio.mimetypes' => 'Súbor musí byť zvuková nahrávka (mp3, m4a, wav, webm, ogg…).',
            'consent.accepted' => 'Bez súhlasu účastníkov poradu neukladaj.',
        ]);

        $heldAt = Carbon::parse($this->heldAt);

        $meeting = Auth::user()->meetings()->create([
            'title' => filled($this->title) ? $this->title : Meeting::defaultTitle($heldAt),
            'project_id' => $this->projectId,
            'held_at' => $heldAt,
            'language' => $this->language ?: null,
        ]);

        $extension = $this->audio->guessExtension() ?: ($this->audio->getClientOriginalExtension() ?: 'bin');
        $path = $this->audio->storeAs(
            'meetings/'.Auth::id(),
            $meeting->id.'-'.Str::lower(Str::random(10)).'.'.$extension,
            Meeting::disk(),
        );

        $meeting->forceFill([
            'audio_path' => $path,
            'audio_mime' => $this->audio->getMimeType(),
            'audio_size' => $this->audio->getSize(),
        ])->save();

        $pipeline->process($meeting);

        $this->redirectRoute('meetings.show', $meeting, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.meetings.create');
    }
}
