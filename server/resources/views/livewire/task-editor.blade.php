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

                <flux:input type="date" wire:model="dueDate" label="Termín" />
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
    </flux:modal>

    @include('livewire.partials.priority-conflict')
</div>
