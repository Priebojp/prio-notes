@php($human = fn (int $s) => \App\Support\Duration::human($s))

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Odpracovaný čas</flux:heading>
            <flux:text class="mt-1">
                {{ $this->range()[0]->translatedFormat('j. n.') }} – {{ $this->range()[1]->translatedFormat('j. n. Y') }}
                · spolu <strong>{{ $human($this->total) }}</strong>
            </flux:text>
        </div>
        <flux:select wire:model.live="period" class="max-w-48">
            @foreach (\App\Livewire\TimeReport::PERIODS as $key => $label)
                <flux:select.option :value="$key">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->days->count() > 1)
        @php($max = max(1, $this->days->max()))
        <flux:card class="space-y-1.5">
            @foreach ($this->days as $day => $seconds)
                <div class="flex items-center gap-3 text-sm" wire:key="day-{{ $day }}">
                    <span class="w-20 shrink-0 text-zinc-500">{{ \Carbon\CarbonImmutable::parse($day)->translatedFormat('D j. n.') }}</span>
                    <div class="h-2 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ round($seconds / $max * 100) }}%"></div>
                    </div>
                    <span class="w-20 shrink-0 text-end tabular-nums">{{ $human($seconds) }}</span>
                </div>
            @endforeach
        </flux:card>
    @endif

    @forelse ($this->breakdown as $company)
        <flux:card class="space-y-3" wire:key="company-{{ $loop->index }}">
            <div class="flex items-center justify-between">
                <flux:badge :color="$company->color">{{ $company->name }}</flux:badge>
                <span class="font-medium tabular-nums">{{ $human($company->seconds) }}</span>
            </div>
            @foreach ($company->children as $project)
                <div class="space-y-1">
                    <div class="flex justify-between text-sm font-medium">
                        <span>{{ $project->name }}</span>
                        <span class="tabular-nums">{{ $human($project->seconds) }}</span>
                    </div>
                    @foreach ($project->children as $task)
                        <button type="button" class="flex w-full cursor-pointer justify-between ps-4 text-sm text-zinc-500 hover:text-zinc-900 dark:hover:text-white"
                            x-on:click="$dispatch('edit-task', { id: {{ $task->taskId }} })">
                            <span class="truncate">{{ $task->name }}</span>
                            <span class="shrink-0 ps-3 tabular-nums">{{ $human($task->seconds) }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </flux:card>
    @empty
        <flux:callout icon="clock" heading="Žiadny zaznamenaný čas">
            Spusti časovač tlačidlom ▶ pri úlohe alebo zapíš čas ručne v detaile úlohy.
        </flux:callout>
    @endforelse
</div>
