<div class="mx-auto flex w-full max-w-3xl flex-col gap-6" @if ($this->anyProcessing) wire:poll.5s @endif>
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Porady</flux:heading>
            <flux:text class="mt-1">Nahrávka → prepis → zápis a návrhy úloh.</flux:text>
        </div>
        <flux:button icon="microphone" variant="primary" :href="route('meetings.create')" wire:navigate>Nová porada</flux:button>
    </div>

    <div class="flex flex-col gap-2">
        @forelse ($this->meetings as $meeting)
            <a href="{{ route('meetings.show', $meeting) }}" wire:navigate wire:key="meeting-{{ $meeting->id }}"
                class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500">
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ $meeting->title }}</div>
                    <div class="truncate text-xs text-zinc-500">
                        {{ $meeting->held_at->translatedFormat('j. n. Y H:i') }}
                        @if ($meeting->duration_seconds) · {{ \App\Support\Duration::human($meeting->duration_seconds) }} @endif
                        @if ($meeting->project) · {{ $meeting->project->company->name }} · {{ $meeting->project->name }} @endif
                    </div>
                </div>
                @if ($n = $meeting->pending_items_count)
                    <flux:badge size="sm" color="sky">{{ $n }} {{ $n === 1 ? 'návrh úlohy' : ($n < 5 ? 'návrhy úloh' : 'návrhov úloh') }}</flux:badge>
                @endif
                <flux:badge size="sm" :color="$meeting->status->color()" :icon="$meeting->status->isProcessing() ? 'arrow-path' : null">
                    {{ $meeting->status->label() }}
                </flux:badge>
            </a>
        @empty
            <flux:callout icon="microphone" heading="Zatiaľ žiadne porady">
                Nahraj poradu priamo v prehliadači alebo nahraj súbor z telefónu. Účastníci musia o nahrávaní vedieť.
            </flux:callout>
        @endforelse
    </div>

    {{ $this->meetings->links() }}
</div>
