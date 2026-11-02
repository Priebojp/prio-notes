<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:text>
                <flux:link :href="route('projects.index')" wire:navigate variant="subtle">{{ $project->company->name }}</flux:link>
            </flux:text>
            <flux:heading size="xl">
                {{ $project->name }}
                @if ($project->archived_at)
                    <flux:badge size="sm">archív</flux:badge>
                @endif
            </flux:heading>
        </div>
        <div class="flex gap-2">
            <flux:modal.trigger name="project-settings">
                <flux:button variant="ghost" icon="cog-6-tooth" aria-label="Nastavenia projektu" />
            </flux:modal.trigger>
            <flux:button icon="plus" x-on:click="$dispatch('edit-task', { projectId: {{ $project->id }} })">Nová úloha</flux:button>
        </div>
    </div>

    <div class="flex flex-col gap-2">
        @forelse ($this->openTasks as $task)
            <x-task-row :task="$task" wire:key="open-{{ $task->id }}" />
        @empty
            <flux:text>Žiadne otvorené úlohy.</flux:text>
        @endforelse
    </div>

    @if ($this->doneTasks->isNotEmpty())
        <div class="flex flex-col gap-2">
            <flux:heading>Hotové</flux:heading>
            @foreach ($this->doneTasks as $task)
                <x-task-row :task="$task" :show-priority="false" wire:key="done-{{ $task->id }}" />
            @endforeach
        </div>
    @endif

    <flux:modal name="project-settings" class="md:w-96">
        <form wire:submit="rename" class="space-y-5">
            <flux:heading size="lg">Projekt</flux:heading>
            <flux:input wire:model="name" label="Názov" required />
            <div class="flex gap-2">
                <flux:button type="button" variant="ghost" wire:click="toggleArchive">
                    {{ $project->archived_at ? 'Obnoviť z archívu' : 'Archivovať' }}
                </flux:button>
                <flux:spacer />
                <flux:button type="submit" variant="primary">Uložiť</flux:button>
            </div>
        </form>
    </flux:modal>

    @include('livewire.partials.priority-conflict')
</div>
