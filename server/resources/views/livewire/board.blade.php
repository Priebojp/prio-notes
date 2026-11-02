<div class="flex w-full flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Priority</flux:heading>
            <flux:text class="mt-1">Presúvaj úlohy myšou alebo šípkami. P1 a P2 majú obmedzený počet miest.</flux:text>
        </div>
        <flux:button icon="plus" x-on:click="$dispatch('edit-task')">Nová úloha</flux:button>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (\App\Enums\Priority::cases() as $priority)
            @php($column = $this->columns[$priority->value])
            <section class="flex flex-col gap-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900/60" wire:key="column-{{ $priority->value }}">
                <div class="flex items-center justify-between px-1">
                    <flux:heading>{{ $priority->label() }}</flux:heading>
                    <flux:badge size="sm" :color="$priority->color()">
                        {{ $column->count() }}{{ $priority->capacity() ? ' / '.$priority->capacity() : '' }}
                    </flux:badge>
                </div>

                <div class="flex min-h-16 flex-col gap-2"
                    wire:sort="sort" wire:sort:group="board" wire:sort:group-id="{{ $priority->value }}">
                    @foreach ($column as $task)
                        <x-task-row :task="$task" :show-priority="false"
                            wire:key="card-{{ $task->id }}" wire:sort:item="{{ $task->id }}" class="cursor-grab">
                            <div class="flex flex-col opacity-40 group-hover:opacity-100" wire:sort:ignore>
                                <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveUp({{ $task->id }})" aria-label="Vyššie" />
                                <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveDown({{ $task->id }})" aria-label="Nižšie" />
                            </div>
                        </x-task-row>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @include('livewire.partials.priority-conflict')
</div>
