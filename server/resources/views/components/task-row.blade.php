@props(['task', 'showPriority' => true])

@php
    $due = $task->due_date;
    $dueColor = match (true) {
        $due === null => null,
        $due->lt(today()) => 'red',
        $due->isToday() => 'orange',
        $due->lte(today()->addDays(config('prio.due_soon_days'))) => 'amber',
        default => 'zinc',
    };
@endphp

<div {{ $attributes->class('group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900') }}>
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
        @if ($due)
            <flux:badge size="sm" :color="$dueColor" icon="calendar">
                {{ $due->isToday() ? 'dnes' : ($due->isTomorrow() ? 'zajtra' : $due->translatedFormat('j. n.')) }}
            </flux:badge>
        @endif
        @if ($showPriority)
            <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
        @endif
        {{ $slot }}
    </div>
</div>
