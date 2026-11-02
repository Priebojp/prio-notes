<div>
    @if ($entry = $this->entry)
        <div class="fixed inset-x-4 bottom-4 z-40 flex items-center gap-3 rounded-xl border border-emerald-500/60 bg-white p-3 shadow-lg sm:inset-x-auto sm:right-6 sm:max-w-sm dark:bg-zinc-900"
            wire:key="running-{{ $entry->id }}">
            <span class="relative flex size-2.5 shrink-0">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"></span>
            </span>
            <button type="button" class="min-w-0 flex-1 cursor-pointer text-start"
                x-on:click="$dispatch('edit-task', { id: {{ $entry->task_id }} })">
                <div class="truncate text-sm font-medium">{{ $entry->task->title }}</div>
                @if ($entry->task->project)
                    <div class="truncate text-xs text-zinc-500">{{ $entry->task->project->company->name }} · {{ $entry->task->project->name }}</div>
                @endif
            </button>
            <x-elapsed :since="$entry->started_at" class="font-mono text-sm" />
            <flux:button size="sm" variant="primary" color="emerald" icon="stop" wire:click="stop">Stop</flux:button>
        </div>
    @endif
</div>
