@php($conflictPriorityEnum = $conflictPriority ? \App\Enums\Priority::from($conflictPriority) : null)

<flux:modal :name="$this->conflictModalName()" class="md:w-[28rem]" @close="cancelConflict">
    @if ($conflictPriorityEnum)
        <form wire:submit="resolveConflict" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $conflictPriorityEnum->label() }} je plná</flux:heading>
                <flux:text class="mt-2">
                    Obsadené {{ $conflictPriorityEnum->capacity() }}/{{ $conflictPriorityEnum->capacity() }}.
                    Ktorá úloha sa posunie na {{ $conflictPriorityEnum->lower()->label() }}?
                </flux:text>
            </div>

            <flux:radio.group wire:model="demoteId" variant="cards" class="flex-col">
                @foreach ($this->conflictOccupants as $occupant)
                    <flux:radio :value="$occupant->id" wire:key="occupant-{{ $occupant->id }}"
                        :label="$occupant->title"
                        :description="$occupant->project ? $occupant->project->company->name.' · '.$occupant->project->name : null" />
                @endforeach
            </flux:radio.group>

            <flux:error name="demoteId" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="cancelConflict">Zrušiť</flux:button>
                <flux:button type="submit" variant="primary">Posunúť a uložiť</flux:button>
            </div>
        </form>
    @endif
</flux:modal>
