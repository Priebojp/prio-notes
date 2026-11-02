@props(['task', 'showPriority' => true, 'running' => null])

@php
    $due = $task->due_date;
    $dueColor = match (true) {
        $due === null => null,
        $due->lt(today()) => 'red',
        $due->isToday() => 'orange',
        $due->lte(today()->addDays(config('prio.due_soon_days'))) => 'amber',
        default => 'zinc',
    };
    $isRunning = $running?->task_id === $task->id;
    $tracked = (int) ($task->tracked_seconds ?? 0);
@endphp

<div {{ $attributes->class([
    'group flex items-center gap-3 rounded-lg border bg-white px-3 py-2 dark:bg-zinc-900',
    'border-zinc-200 dark:border-zinc-700' => ! $isRunning,
    'border-emerald-500 dark:border-emerald-500' => $isRunning,
]) }}>
    @if ($task->isDone())
        <flux:checkbox checked wire:click="reopen({{ $task->id }})" aria-label="Znovu otvoriť" />
    @else
        <flux:checkbox wire:click="complete({{ $task->id }})" aria-label="Hotovo" />
    @endif

    <button type="button" class="min-w-0 flex-1 cursor-pointer text-start"
        x-on:click="$dispatch('edit-task', { id: {{ $task->id }} })">
        <div @class(['truncate text-sm font-medium', 'text-zinc-400 line-through' => $task->isDone()])>{{ $task->title }}</div>
        @if ($task->project)
            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                {{ $task->project->company->name }} · {{ $task->project->name }}
            </div>
        @endif
    </button>

    <div class="flex shrink-0 items-center gap-1.5">
        @if ($tracked > 0 && ! $isRunning)
            <span class="hidden text-xs tabular-nums text-zinc-500 sm:inline">{{ \App\Support\Duration::human($tracked) }}</span>
        @endif
        @if ($due)
            <flux:badge size="sm" :color="$dueColor" icon="calendar">
                {{ $due->isToday() ? 'dnes' : ($due->isTomorrow() ? 'zajtra' : $due->translatedFormat('j. n.')) }}
            </flux:badge>
        @endif
        @if ($showPriority)
            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
        @endif
        @unless ($task->isDone())
            @if ($isRunning)
                <x-elapsed :since="$running->started_at" :offset="$tracked" class="text-xs font-medium text-emerald-600 dark:text-emerald-400" />
                <flux:button size="xs" variant="primary" color="emerald" icon="stop" wire:click="stopTimer" aria-label="Zastaviť čas" />
            @else
                <flux:button size="xs" variant="ghost" icon="play" wire:click="startTimer({{ $task->id }})" aria-label="Spustiť čas"
                    class="sm:opacity-40 sm:group-hover:opacity-100" />
            @endif
        @endunless
        {{ $slot }}
    </div>
</div>
