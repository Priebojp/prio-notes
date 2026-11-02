<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">Denný e-mail</flux:heading>

    <x-settings.layout heading="Denný e-mail" subheading="Ráno prehľad toho, čo je na rade: úlohy na dnes, blížiace sa termíny, nevybavené porady a včera odpracovaný čas.">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:switch wire:model="enabled" label="Posielať denný e-mail" align="left" />

            <flux:time-picker wire:model="time" locale="sk-SK" label="Čas odoslania" description="Najskôr o tomto čase, raz za deň." />

            <flux:switch wire:model="weekends" label="Posielať aj cez víkend" align="left" />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit">Uložiť</flux:button>
                <flux:button type="button" icon="paper-airplane" wire:click="sendTest">Poslať skúšobný e-mail teraz</flux:button>
            </div>
        </form>
    </x-settings.layout>
</section>
