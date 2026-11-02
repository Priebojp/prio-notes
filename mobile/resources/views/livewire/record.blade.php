<div class="space-y-6" @if (collect($meetings)->contains(fn ($m) => in_array($m['status'], ['uploaded', 'transcribing', 'summarizing']))) wire:poll.15s="loadLists" @endif>
    <header>
        <h1 class="text-2xl font-semibold">Porada</h1>
        <p class="text-sm text-zinc-500">Nahraj poradu, prepis a zápis s úlohami pripraví server.</p>
    </header>

    @if ($error)
        <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</p>
    @endif

    <section class="flex flex-col items-center gap-5 rounded-3xl bg-white px-4 py-8 dark:bg-zinc-900"
        x-data="{ seconds: 0 }"
        x-init="setInterval(() => { if ($wire.state === 'recording') seconds++; if ($wire.state === 'idle') seconds = 0 }, 1000)">

        <div class="font-mono text-5xl tabular-nums"
            x-text="(seconds >= 3600 ? Math.floor(seconds / 3600) + ':' : '') + String(Math.floor(seconds % 3600 / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0')"></div>

        @switch($state)
            @case('idle')
                <button type="button" wire:click="start" class="flex size-24 items-center justify-center rounded-full bg-red-600 text-white shadow-lg active:bg-red-700" aria-label="Začať nahrávať">
                    <svg class="size-10" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" /></svg>
                </button>
                <p class="text-sm text-zinc-500">Ťukni a začni nahrávať. Nahráva aj pri zhasnutom displeji.</p>
                @break

            @case('recording')
            @case('paused')
                <div class="flex items-center gap-2 text-sm {{ $state === 'recording' ? 'text-red-600' : 'text-zinc-500' }}">
                    <span class="size-2.5 rounded-full {{ $state === 'recording' ? 'animate-pulse bg-red-600' : 'bg-zinc-400' }}"></span>
                    {{ $state === 'recording' ? 'Nahráva sa…' : 'Pozastavené' }}
                </div>
                <div class="grid w-full grid-cols-2 gap-3">
                    @if ($state === 'recording')
                        <button type="button" wire:click="pause" class="rounded-xl bg-zinc-100 py-3 font-semibold dark:bg-zinc-800">Pauza</button>
                    @else
                        <button type="button" wire:click="resume" class="rounded-xl bg-zinc-100 py-3 font-semibold dark:bg-zinc-800">Pokračovať</button>
                    @endif
                    <button type="button" wire:click="stop" class="rounded-xl bg-red-600 py-3 font-semibold text-white">Ukončiť</button>
                </div>
                @break

            @case('stopping')
                <p class="text-sm text-zinc-500">Ukladá sa nahrávka…</p>
                @break

            @case('recorded')
            @case('uploading')
                <form wire:submit="upload" class="w-full space-y-3">
                    <input type="text" wire:model="title" placeholder="Názov (nepovinné, AI navrhne)"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base dark:border-zinc-700 dark:bg-zinc-950">
                    <div class="grid grid-cols-2 gap-3">
                        <select wire:model="projectId" class="rounded-xl border border-zinc-300 bg-white px-3 py-3 text-base dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">Bez projektu</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project['id'] }}">{{ $project['company'] }} · {{ $project['name'] }}</option>
                            @endforeach
                        </select>
                        <select wire:model="language" class="rounded-xl border border-zinc-300 bg-white px-3 py-3 text-base dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="sk">Slovenčina</option>
                            <option value="cs">Čeština</option>
                            <option value="">Automaticky</option>
                        </select>
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="consent" class="mt-0.5 size-5 accent-emerald-600">
                        Všetci účastníci vedia o nahrávaní a súhlasia s ním.
                    </label>
                    @error('consent') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <button type="button" wire:click="discard" wire:confirm="Zahodiť nahrávku?" class="rounded-xl bg-zinc-100 py-3 font-semibold dark:bg-zinc-800">Zahodiť</button>
                        <button type="submit" @disabled($state === 'uploading') class="rounded-xl bg-emerald-600 py-3 font-semibold text-white disabled:opacity-60">
                            {{ $state === 'uploading' ? 'Odosiela sa…' : 'Odoslať' }}
                        </button>
                    </div>
                </form>
                @break

            @case('done')
                <p class="text-center text-sm">Odoslané. Prepis a zápis budú o pár minút na webe.</p>
                <button type="button" wire:click="discard" class="rounded-xl bg-zinc-100 px-6 py-3 font-semibold dark:bg-zinc-800">Nová nahrávka</button>
                @break
        @endswitch
    </section>

    @if ($meetings)
        <section class="space-y-2">
            <h2 class="text-sm font-semibold text-zinc-500">Posledné porady</h2>
            @foreach ($meetings as $meeting)
                <div wire:key="m-{{ $meeting['id'] }}" class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 dark:bg-zinc-900">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[15px] font-medium">{{ $meeting['title'] }}</div>
                        <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($meeting['held_at'])->translatedFormat('j. n. H:i') }}
                            @if ($meeting['pending_items_count']) · {{ $meeting['pending_items_count'] }} na rozhodnutie @endif
                        </div>
                    </div>
                    <span @class(['shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $meeting['status'] === 'ready',
                        'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' => $meeting['status'] === 'failed',
                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => ! in_array($meeting['status'], ['ready', 'failed'])])>
                        {{ $meeting['status_label'] }}
                    </span>
                </div>
            @endforeach
        </section>
    @endif
</div>
