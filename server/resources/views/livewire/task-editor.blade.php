<div>
    <flux:modal name="task-editor" class="w-full md:w-[34rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $taskId ? 'Upraviť úlohu' : 'Nová úloha' }}</flux:heading>

            <flux:input wire:model="title" label="Názov" required autofocus />

            <flux:select wire:model="projectId" label="Projekt">
                <flux:select.option value="">— bez projektu —</flux:select.option>
                @foreach ($this->projects as $project)
                    <flux:select.option :value="$project->id">{{ $project->company->name }} · {{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="priority" label="Priorita">
                    @foreach (\App\Enums\Priority::cases() as $case)
                        <flux:select.option :value="$case->value">
                            {{ $case->label() }}{{ $case->capacity() ? ' (max '.$case->capacity().')' : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:date-picker wire:model="dueDate" label="Termín" locale="sk-SK" start-day="1" clearable with-today />
            </div>

            <flux:input type="number" wire:model="estimateMinutes" label="Odhad (minúty)" min="1" />

            <flux:textarea wire:model="notes" label="Poznámky" rows="5" />

            <div class="flex gap-2">
                @if ($taskId)
                    <flux:button type="button" variant="danger" icon="trash" wire:click="delete"
                        wire:confirm="Naozaj zmazať túto úlohu?">Zmazať</flux:button>
                @endif
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Zrušiť</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Uložiť</flux:button>
            </div>
        </form>

        @if ($taskId)
            @php($running = $this->runningEntry?->task_id === $taskId ? $this->runningEntry : null)
            <div class="mt-8 space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <flux:heading>Odpracovaný čas</flux:heading>
                    <flux:badge size="sm">{{ \App\Support\Duration::human($this->trackedSeconds) }}{{ $estimateMinutes ? ' / odhad '.\App\Support\Duration::human($estimateMinutes * 60) : '' }}</flux:badge>
                    <flux:spacer />
                    @if ($running)
                        <x-elapsed :since="$running->started_at" class="text-sm text-emerald-600 dark:text-emerald-400" />
                        <flux:button size="sm" variant="primary" color="emerald" icon="stop" wire:click="stopTimer">Stop</flux:button>
                    @else
                        <flux:button size="sm" icon="play" wire:click="startTimer({{ $taskId }})">Spustiť</flux:button>
                    @endif
                </div>

                <form wire:submit="logTime" class="flex flex-wrap items-start gap-2">
                    <flux:input type="number" wire:model="logMinutes" placeholder="min" min="1" class="max-w-24" aria-label="Minúty" />
                    <flux:date-picker wire:model="logDate" class="max-w-40" aria-label="Deň" locale="sk-SK" start-day="1" max="today" />
                    <div class="min-w-32 flex-1">
                        <flux:input wire:model="logNote" placeholder="Poznámka (nepovinné)" aria-label="Poznámka" />
                    </div>
                    <flux:button type="submit" icon="plus">Zapísať</flux:button>
                </form>
                <flux:error name="logMinutes" />
                <flux:error name="logDate" />

                @if ($this->timeEntries->isNotEmpty())
                    <div class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        @foreach ($this->timeEntries as $entry)
                            <div class="flex items-center gap-3 py-1.5" wire:key="entry-{{ $entry->id }}">
                                <span class="w-28 shrink-0 text-zinc-500">{{ $entry->started_at->translatedFormat('j. n. H:i') }}</span>
                                <span class="w-20 shrink-0 tabular-nums">{{ $entry->isRunning() ? 'beží…' : \App\Support\Duration::human($entry->seconds) }}</span>
                                <span class="min-w-0 flex-1 truncate text-zinc-500">{{ $entry->note }}</span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="deleteEntry({{ $entry->id }})"
                                    wire:confirm="Zmazať tento záznam času?" aria-label="Zmazať záznam" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    @include('livewire.partials.priority-conflict')
</div>
