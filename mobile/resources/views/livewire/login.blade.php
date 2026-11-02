<div class="flex min-h-[80vh] flex-col justify-center gap-8">
    <div class="text-center">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-emerald-600 text-2xl font-bold text-white">P</div>
        <h1 class="text-2xl font-semibold">Prio Notes</h1>
        <p class="mt-1 text-sm text-zinc-500">Prihlás sa rovnakým účtom ako na webe.</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        @foreach ([['serverUrl', 'Server', 'url', 'https://note.prieboj.sk'], ['email', 'E-mail', 'email', ''], ['password', 'Heslo', 'password', '']] as [$field, $label, $type, $placeholder])
            <label class="block">
                <span class="mb-1 block text-sm font-medium">{{ $label }}</span>
                <input type="{{ $type }}" wire:model="{{ $field }}" placeholder="{{ $placeholder }}" autocapitalize="off" autocomplete="{{ $field === 'password' ? 'current-password' : 'off' }}"
                    class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base dark:border-zinc-700 dark:bg-zinc-900">
                @error($field) <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        @endforeach

        @if ($needsCode)
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Kód z autentifikačnej aplikácie</span>
                <input type="text" inputmode="numeric" wire:model="code" autofocus
                    class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-center text-xl tracking-widest dark:border-zinc-700 dark:bg-zinc-900">
            </label>
        @endif

        @if ($error)
            <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</p>
        @endif

        <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-xl bg-emerald-600 py-3 text-base font-semibold text-white active:bg-emerald-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="login">Prihlásiť sa</span>
            <span wire:loading wire:target="login">Prihlasujem…</span>
        </button>
    </form>
</div>
