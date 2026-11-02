<?php

namespace App\Livewire\Meetings;

use App\Enums\ActionItemStatus;
use App\Enums\Priority;
use App\Livewire\Concerns\InteractsWithTasks;
use App\Meetings\MeetingPipeline;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read EloquentCollection<int, MeetingActionItem> $actionItems
 */
class Show extends Component
{
    use InteractsWithTasks;

    public Meeting $meeting;

    public string $title = '';

    /** @var array<string, string> speaker id => display name */
    public array $speakerNames = [];

    /** @var array<int, array{title: string, due_date: string|null, priority: int}> editable pending suggestions */
    public array $items = [];

    public function mount(Meeting $meeting): void
    {
        abort_unless($meeting->user_id === Auth::id(), 404);

        $this->meeting = $meeting;
        $this->fillForms();
    }

    /** Polled while the recording is being processed. */
    public function checkStatus(): void
    {
        $wasProcessing = $this->meeting->status->isProcessing();
        $this->meeting->refresh();

        if ($wasProcessing && ! $this->meeting->status->isProcessing()) {
            $this->fillForms();
            unset($this->actionItems);
        }
    }

    /** @return EloquentCollection<int, MeetingActionItem> */
    #[Computed]
    public function actionItems(): EloquentCollection
    {
        return $this->meeting->actionItems()->with('task')->orderBy('id')->get();
    }

    #[Computed]
    public function summaryHtml(): string
    {
        return Str::markdown((string) $this->meeting->summary, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /** The minutes as Markdown, for pasting into e-mail or chat. */
    #[Computed]
    public function minutesText(): string
    {
        $m = $this->meeting;
        $lines = ["# {$m->title}", $m->held_at->translatedFormat('j. n. Y H:i'), '', (string) $m->summary];

        foreach (['Rozhodnutia' => $m->decisions, 'Otvorené otázky' => $m->open_questions] as $heading => $list) {
            if (! empty($list)) {
                $lines = [...$lines, '', "## {$heading}", ...array_map(fn ($line) => "- {$line}", $list)];
            }
        }

        $items = $this->actionItems->reject(fn (MeetingActionItem $i) => $i->status === ActionItemStatus::Dismissed);
        if ($items->isNotEmpty()) {
            $lines = [...$lines, '', '## Úlohy', ...$items->map(fn (MeetingActionItem $i) => '- '.$i->title
                .($i->owner ? " — {$i->owner}" : '')
                .($i->due_date ? ' (do '.$i->due_date->translatedFormat('j. n.').')' : ''))->all()];
        }

        return implode("\n", $lines);
    }

    public function accept(int $id): bool
    {
        $item = $this->meeting->actionItems()->where('status', ActionItemStatus::Pending)->findOrFail($id);

        $this->validate([
            "items.{$id}.title" => ['required', 'string', 'max:255'],
            "items.{$id}.due_date" => ['nullable', 'date'],
            "items.{$id}.priority" => ['required', Rule::enum(Priority::class)],
        ], ["items.{$id}.title.required" => 'Úloha potrebuje názov.']);

        $data = $this->items[$id];
        $context = 'Z porady „'.$this->meeting->title.'“ ('.$this->meeting->held_at->translatedFormat('j. n. Y').')'
            .($item->owner ? '. Zodpovedný: '.$item->owner : '').'.';

        $task = $this->board()->createInBacklog(Auth::user(), [
            'title' => $data['title'],
            'notes' => trim(($item->notes ? $item->notes."\n\n" : '').$context),
            'project_id' => $this->meeting->project_id,
            'meeting_id' => $this->meeting->id,
            'due_date' => $data['due_date'] ?: null,
        ]);

        $item->update([
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?: null,
            'priority' => $data['priority'],
            'status' => ActionItemStatus::Accepted,
        ]);
        $item->task()->associate($task)->save();

        unset($this->items[$id], $this->actionItems);

        return $this->attempt(fn () => $this->board()->place($task, Priority::from((int) $data['priority'])));
    }

    /** Accept every pending suggestion; stops at the first full priority to ask what to demote. */
    public function acceptAll(): void
    {
        $created = 0;

        foreach (array_keys($this->items) as $id) {
            $created++;

            if (! $this->accept($id)) {
                return;
            }
        }

        Flux::toast(variant: 'success', text: "Vytvorené úlohy: {$created}.");
    }

    public function dismiss(int $id): void
    {
        $this->meeting->actionItems()->findOrFail($id)->update(['status' => ActionItemStatus::Dismissed]);
        unset($this->items[$id], $this->actionItems);
    }

    public function restore(int $id): void
    {
        $item = $this->meeting->actionItems()->where('status', ActionItemStatus::Dismissed)->findOrFail($id);
        $item->update(['status' => ActionItemStatus::Pending]);
        $this->items[$id] = $this->itemForm($item);
        unset($this->actionItems);
    }

    public function saveSpeakers(MeetingPipeline $pipeline): void
    {
        $this->validate(['speakerNames.*' => ['required', 'string', 'max:60']]);

        $this->meeting->forceFill([
            'speakers' => array_intersect_key($this->speakerNames, $this->meeting->speakers ?? []),
        ])->save();

        $pipeline->summarize($this->meeting);
        Flux::toast(text: 'Mená uložené, zápis sa pripravuje znova.');
    }

    public function rename(): void
    {
        $this->validate(['title' => ['required', 'string', 'max:255']]);
        $this->meeting->update(['title' => $this->title]);

        Flux::modal('meeting-settings')->close();
    }

    public function retry(MeetingPipeline $pipeline): void
    {
        $pipeline->retry($this->meeting);
    }

    public function deleteAudio(): void
    {
        $this->meeting->deleteAudio();

        Flux::modal('meeting-settings')->close();
        Flux::toast(text: 'Nahrávka zmazaná, prepis a zápis zostali.');
    }

    public function delete(): void
    {
        $this->meeting->deleteAudio();
        $this->meeting->delete();

        $this->redirectRoute('meetings.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.meetings.show')->title($this->meeting->title);
    }

    private function fillForms(): void
    {
        $this->title = $this->meeting->title;
        $this->speakerNames = $this->meeting->speakers ?? [];
        $this->items = $this->meeting->actionItems()
            ->where('status', ActionItemStatus::Pending)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (MeetingActionItem $item) => [$item->id => $this->itemForm($item)])
            ->all();
    }

    /** @return array{title: string, due_date: string|null, priority: int} */
    private function itemForm(MeetingActionItem $item): array
    {
        return [
            'title' => $item->title,
            'due_date' => $item->due_date?->toDateString(),
            'priority' => $item->priority->value,
        ];
    }
}
