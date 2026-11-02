<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="pb-28">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
