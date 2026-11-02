<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <flux:heading size="xl">Firmy a projekty</flux:heading>
        <flux:button icon="plus" wire:click="editCompany">Nová firma</flux:button>
    </div>

    @forelse ($this->companies as $company)
        <flux:card wire:key="company-{{ $company->id }}" class="space-y-3">
            <div class="flex items-center gap-3">
                <flux:badge :color="$company->color">{{ $company->name }}</flux:badge>
                <flux:spacer />
                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editCompany({{ $company->id }})" aria-label="Upraviť firmu" />
                <flux:button size="sm" icon="plus" wire:click="newProject({{ $company->id }})">Projekt</flux:button>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($company->projects as $project)
                    <a href="{{ route('projects.show', $project) }}" wire:navigate wire:key="project-{{ $project->id }}"
                        @class(['flex items-center justify-between py-2 text-sm hover:text-zinc-950 dark:hover:text-white', 'opacity-50' => $project->archived_at])>
                        <span>{{ $project->name }}{{ $project->archived_at ? ' (archív)' : '' }}</span>
                        <span class="text-zinc-500">{{ $project->tasks_count }} otvorených</span>
                    </a>
                @empty
                    <flux:text class="py-2">Zatiaľ žiadne projekty.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @empty
        <flux:callout icon="building-office" heading="Začni firmou">
            Úlohy sa delia podľa firmy a projektu. Pridaj prvú firmu, napríklad „Exteco“ alebo „Osobné“.
        </flux:callout>
    @endforelse

    <flux:modal name="company-form" class="md:w-96">
        <form wire:submit="saveCompany" class="space-y-5">
            <flux:heading size="lg">{{ $companyId ? 'Upraviť firmu' : 'Nová firma' }}</flux:heading>
            <flux:input wire:model="companyName" label="Názov" required />
            <flux:select wire:model="companyColor" label="Farba">
                @foreach (\App\Models\Company::COLORS as $color)
                    <flux:select.option :value="$color">{{ $color }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Uložiť</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="project-form" class="md:w-96">
        <form wire:submit="saveProject" class="space-y-5">
            <flux:heading size="lg">Nový projekt</flux:heading>
            <flux:input wire:model="projectName" label="Názov" required />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Vytvoriť</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
