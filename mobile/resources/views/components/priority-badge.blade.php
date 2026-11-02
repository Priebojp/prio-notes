@props(['priority', 'label' => null])

@php
    $classes = match ((int) $priority) {
        1 => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
        2 => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        3 => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->class("inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-semibold {$classes}") }}>
    {{ $label ?? ((int) $priority === 4 ? 'Backlog' : 'P'.$priority) }}
</span>
