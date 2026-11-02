@php
    $status = $meeting->status;
    $speakerColors = ['text-sky-600 dark:text-sky-400', 'text-amber-600 dark:text-amber-400', 'text-emerald-600 dark:text-emerald-400', 'text-pink-600 dark:text-pink-400', 'text-violet-600 dark:text-violet-400', 'text-orange-600 dark:text-orange-400'];
    $speakerIndex = array_flip(array_keys($meeting->speakers ?? []));
    $pending = $this->actionItems->where('status', \App\Enums\ActionItemStatus::Pending);
@endphp

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6"
    x-data="{ seek(t) { const p = $refs.player; if (p) { p.currentTime = t; p.play() } } }"
    @if ($status->isProcessing()) wire:poll.5s="checkStatus" @endif>

    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:text>
                <flux:link :href="route('meetings.index')" wire:navigate variant="subtle">Porady</flux:link>
                @if ($meeting->project) · {{ $meeting->project->company->name }} · {{ $meeting->project->name }} @endif
            </flux:text>
            <flux:heading size="xl" class="truncate">{{ $meeting->title }}</flux:heading>
            <flux:text class="mt-1">
                {{ $meeting->held_at->translatedFormat('l j. n. Y H:i') }}
                @if ($meeting->duration_seconds) · {{ \App\Support\Duration::human($meeting->duration_seconds) }} @endif
            </flux:text>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <flux:badge :color="$status->color()">{{ $status->label() }}</flux:badge>
            <flux:modal.trigger name="meeting-settings">
                <flux:button variant="ghost" icon="cog-6-tooth" aria-label="Nastavenia porady" />
            </flux:modal.trigger>
        </div>
    </div>

    @if ($meeting->hasAudio())
        <audio x-ref="player" controls preload="metadata" class="w-full" src="{{ route('meetings.audio', $meeting) }}" wire:ignore></audio>
    @elseif ($meeting->audio_deleted_at)
        <flux:text class="text-sm">Nahrávka bola zmazaná {{ $meeting->audio_deleted_at->translatedFormat('j. n. Y') }}.</flux:text>
    @endif

    @if ($status->isProcessing())
        <flux:callout icon="arrow-path" color="amber" heading="{{ $status->label() }}…">
            Pri hodinovej porade to trvá pár minút. Stránka sa obnoví sama, môžeš zatiaľ robiť niečo iné.
        </flux:callout>
    @elseif ($status === \App\Enums\MeetingStatus::Failed)
        <flux:callout icon="exclamation-triangle" color="red" heading="Spracovanie zlyhalo">
            <flux:callout.text>{{ $meeting->error }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" icon="arrow-path" wire:click="retry">Skúsiť znova</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($meeting->processed_at || $meeting->transcript)
        <flux:tab.group>
            <flux:tabs>
                <flux:tab name="minutes" icon="document-text">Zápis</flux:tab>
                <flux:tab name="tasks" icon="check-circle">Úlohy @if ($pending->count()) <flux:badge size="sm" color="sky" class="ms-1">{{ $pending->count() }}</flux:badge> @endif</flux:tab>
                <flux:tab name="transcript" icon="chat-bubble-left-right">Prepis</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="minutes" class="space-y-6">
                @if ($meeting->summary)
                    <div class="flex justify-end">
                        <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($this->minutesText)); $flux.toast('Zápis skopírovaný.')">Kopírovať zápis</flux:button>
                    </div>
                    <div class="space-y-3 text-sm leading-relaxed [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:ps-5 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-5">
                        {!! $this->summaryHtml !!}
                    </div>
                @else
                    <flux:text>Zápis zatiaľ nie je pripravený.</flux:text>
                @endif

                @foreach (['Rozhodnutia' => [$meeting->decisions, 'check-badge'], 'Otvorené otázky' => [$meeting->open_questions, 'question-mark-circle']] as $heading => [$list, $icon])
                    @if (! empty($list))
                        <flux:card class="space-y-2">
                            <flux:heading class="flex items-center gap-2"><flux:icon :name="$icon" variant="micro" /> {{ $heading }}</flux:heading>
                            <ul class="list-disc space-y-1 ps-5 text-sm">
                                @foreach ($list as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        </flux:card>
                    @endif
                @endforeach
            </flux:tab.panel>

            <flux:tab.panel name="tasks" class="space-y-3">
                @if ($pending->count() > 1)
                    <div class="flex justify-end">
                        <flux:button size="sm" variant="primary" icon="check" wire:click="acceptAll">Vytvoriť všetky ({{ $pending->count() }})</flux:button>
                    </div>
                @endif

                @forelse ($this->actionItems as $item)
                    @if ($item->status === \App\Enums\ActionItemStatus::Pending && isset($items[$item->id]))
                        <flux:card class="space-y-3" wire:key="item-{{ $item->id }}">
                            <flux:input wire:model="items.{{ $item->id }}.title" aria-label="Názov úlohy" />
                            <flux:error name="items.{{ $item->id }}.title" />
                            @if ($item->notes)
                                <flux:text class="text-sm">{{ $item->notes }}</flux:text>
                            @endif
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($item->owner)
                                    <flux:badge size="sm" icon="user">{{ $item->owner }}</flux:badge>
                                @endif
                                <flux:spacer />
                                <flux:date-picker wire:model="items.{{ $item->id }}.due_date" size="sm" placeholder="Termín" clearable locale="sk-SK" start-day="1" class="w-44" />
                                <flux:select wire:model="items.{{ $item->id }}.priority" size="sm" class="max-w-28">
                                    @foreach (\App\Enums\Priority::cases() as $case)
                                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:button size="sm" variant="ghost" wire:click="dismiss({{ $item->id }})">Zahodiť</flux:button>
                                <flux:button size="sm" variant="primary" icon="plus" wire:click="accept({{ $item->id }})">Vytvoriť úlohu</flux:button>
                            </div>
                        </flux:card>
                    @elseif ($item->status === \App\Enums\ActionItemStatus::Accepted)
                        <div class="flex items-center gap-3 px-1 text-sm" wire:key="item-{{ $item->id }}">
                            <flux:icon name="check-circle" variant="mini" class="text-emerald-500" />
                            @if ($item->task)
                                <button type="button" class="flex-1 cursor-pointer truncate text-start hover:underline"
                                    x-on:click="$dispatch('edit-task', { id: {{ $item->task_id }} })">{{ $item->task->title }}</button>
                                <flux:badge size="sm" :color="$item->task->priority->color()">{{ $item->task->isDone() ? 'hotovo' : $item->task->priority->label() }}</flux:badge>
                            @else
                                <span class="flex-1 truncate text-zinc-500">{{ $item->title }} (úloha zmazaná)</span>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center gap-3 px-1 text-sm text-zinc-400" wire:key="item-{{ $item->id }}">
                            <flux:icon name="x-circle" variant="mini" />
                            <span class="flex-1 truncate line-through">{{ $item->title }}</span>
                            <flux:button size="xs" variant="ghost" wire:click="restore({{ $item->id }})">Vrátiť</flux:button>
                        </div>
                    @endif
                @empty
                    <flux:text>Z porady nevyplynuli žiadne úlohy.</flux:text>
                @endforelse
            </flux:tab.panel>

            <flux:tab.panel name="transcript" class="space-y-6">
                @if (! empty($speakerNames))
                    <form wire:submit="saveSpeakers" class="space-y-3">
                        <flux:text class="text-sm">Pomenuj rečníkov, zápis sa potom pripraví znova s menami.</flux:text>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($speakerNames as $id => $name)
                                <flux:input wire:model="speakerNames.{{ $id }}" size="sm" wire:key="speaker-{{ $id }}"
                                    :aria-label="'Meno pre '.$id" class:input="{{ $speakerColors[($speakerIndex[$id] ?? 0) % count($speakerColors)] }} font-medium" />
                            @endforeach
                        </div>
                        <flux:button type="submit" size="sm" icon="sparkles">Uložiť mená a pripraviť zápis znova</flux:button>
                    </form>
                @endif

                <div class="space-y-3 text-sm">
                    @foreach ($meeting->segments() as $segment)
                        <div class="flex gap-3" wire:key="seg-{{ $loop->index }}">
                            <button type="button" class="w-14 shrink-0 cursor-pointer pt-0.5 text-start font-mono text-xs text-zinc-400 hover:text-zinc-900 dark:hover:text-white"
                                x-on:click="seek({{ $segment->start }})">{{ $segment->timestamp() }}</button>
                            <div class="min-w-0">
                                <span class="font-medium {{ $speakerColors[($speakerIndex[$segment->speaker] ?? 0) % count($speakerColors)] }}">{{ $meeting->speakerName($segment->speaker) }}:</span>
                                {{ $segment->text }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:tab.panel>
        </flux:tab.group>
    @endif

    <flux:modal name="meeting-settings" class="md:w-96">
        <form wire:submit="rename" class="space-y-5">
            <flux:heading size="lg">Porada</flux:heading>
            <flux:input wire:model="title" label="Názov" required />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Uložiť</flux:button>
            </div>
        </form>
        <flux:separator class="my-6" />
        <div class="flex flex-wrap gap-2">
            @if ($meeting->audio_path)
                <flux:button size="sm" icon="speaker-x-mark" wire:click="deleteAudio" wire:confirm="Zmazať nahrávku? Prepis a zápis zostanú.">Zmazať nahrávku</flux:button>
            @endif
            <flux:button size="sm" variant="danger" icon="trash" wire:click="delete" wire:confirm="Zmazať celú poradu? Úlohy, ktoré z nej vznikli, zostanú.">Zmazať poradu</flux:button>
        </div>
    </flux:modal>

    @include('livewire.partials.priority-conflict')
</div>
