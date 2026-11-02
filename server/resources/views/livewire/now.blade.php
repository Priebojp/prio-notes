<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Teraz</flux:heading>
            <flux:text class="mt-1">{{ now()->translatedFormat('l j. F') }} · dnes hotové: {{ $this->doneToday }}</flux:text>
        </div>
        <flux:button icon="plus" x-on:click="$dispatch('edit-task')">Nová úloha</flux:button>
    </div>

    <form wire:submit="quickAdd" class="flex gap-2">
        <div class="flex-1">
            <flux:input wire:model="quickTitle" placeholder="Rýchlo pridať úlohu…" icon="pencil-square" />
        </div>
        <flux:select wire:model="quickPriority" class="max-w-32">
            @foreach (\App\Enums\Priority::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:button type="submit" variant="primary" icon="plus" aria-label="Pridať" />
    </form>
    <flux:error name="quickTitle" />

    <div class="flex flex-wrap gap-2">
        @foreach (\App\Enums\Priority::cases() as $case)
            <flux:badge :color="$case->color()">
                {{ $case->label() }}: {{ $this->counts[$case->value] ?? 0 }}{{ $case->capacity() ? ' / '.$case->capacity() : '' }}
            </flux:badge>
        @endforeach
    </div>

    <div class="flex flex-col gap-2">
        @forelse ($this->tasks as $task)
            <x-task-row :task="$task" :running="$this->runningEntry" wire:key="now-{{ $task->id }}" />
        @empty
            <flux:callout icon="sparkles" heading="Nič urgentné">Všetko hotové. Pridaj úlohu hore.</flux:callout>
        @endforelse
    </div>

    @include('livewire.partials.priority-conflict')
</div>
