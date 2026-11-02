<div class="space-y-5" wire:poll.60s="load">
    <header class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Teraz</h1>
            <p class="text-sm text-zinc-500">{{ ucfirst(now()->translatedFormat('l j. n.')) }}@if ($userName) · {{ $userName }}@endif</p>
        </div>
        <button type="button" wire:click="logout" wire:confirm="Odhlásiť sa?" class="rounded-lg px-2 py-1 text-xs text-zinc-500">Odhlásiť</button>
    </header>

    @if ($error)
        <div class="flex items-center justify-between gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <span>{{ $error }}</span>
            <button type="button" wire:click="load" class="font-semibold">Znova</button>
        </div>
    @endif

    @if ($running)
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-600 px-4 py-3 text-white"
            x-data="{ start: Date.now() / 1000 - {{ (int) $running['elapsed_seconds'] }}, now: Date.now() / 1000 }"
            x-init="setInterval(() => now = Date.now() / 1000, 1000)">
            <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex size-2.5 rounded-full bg-white"></span></span>
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $running['task_title'] }}</span>
            <span class="font-mono text-sm tabular-nums" x-text="(() => { const s = Math.max(0, Math.floor(now - start)); return Math.floor(s / 3600) + ':' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0') })()"></span>
            <button type="button" wire:click="stopTimer" class="rounded-lg bg-white/20 px-3 py-1.5 text-sm font-semibold">Stop</button>
        </div>
    @endif

    <form wire:submit="addTask" class="flex gap-2">
        <input type="text" wire:model="quickTitle" placeholder="Rýchlo pridať úlohu…"
            class="min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-4 py-3 text-base dark:border-zinc-700 dark:bg-zinc-900">
        <select wire:model="quickPriority" class="rounded-xl border border-zinc-300 bg-white px-2 text-base dark:border-zinc-700 dark:bg-zinc-900">
            <option value="1">P1</option><option value="2">P2</option><option value="3">P3</option><option value="4">Backlog</option>
        </select>
        <button type="submit" class="rounded-xl bg-emerald-600 px-4 text-xl font-semibold text-white active:bg-emerald-700">+</button>
    </form>
    @error('quickTitle') <p class="-mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($capacity)
        <div class="flex flex-wrap gap-2">
            @foreach ($capacity as $priority => $c)
                <x-priority-badge :priority="$priority" :label="$c['label'].': '.$c['open'].($c['max'] ? ' / '.$c['max'] : '')" />
            @endforeach
        </div>
    @endif

    <ul class="space-y-2">
        @forelse ($tasks as $task)
            @php
                $isRunning = ($running['task_id'] ?? null) === $task['id'];
                $due = $task['due_date'] ? \Illuminate\Support\Carbon::parse($task['due_date']) : null;
            @endphp
            <li wire:key="task-{{ $task['id'] }}"
                @class(['flex items-center gap-3 rounded-2xl border bg-white px-3 py-3 dark:bg-zinc-900', 'border-emerald-500' => $isRunning, 'border-zinc-200 dark:border-zinc-800' => ! $isRunning])>
                <button type="button" wire:click="complete({{ $task['id'] }})" aria-label="Hotovo"
                    class="size-7 shrink-0 rounded-full border-2 border-zinc-300 active:bg-emerald-500 dark:border-zinc-600"></button>
                <div class="min-w-0 flex-1">
                    <div class="text-[15px] leading-snug font-medium">{{ $task['title'] }}</div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                        @if ($task['project']) <span>{{ $task['project']['company'] }} · {{ $task['project']['name'] }}</span> @endif
                        @if ($due)
                            <span @class(['font-semibold', 'text-red-600' => $due->lt(today()), 'text-orange-600' => $due->isToday()])>
                                {{ $due->isToday() ? 'dnes' : ($due->isTomorrow() ? 'zajtra' : $due->translatedFormat('j. n.')) }}
                            </span>
                        @endif
                    </div>
                </div>
                <x-priority-badge :priority="$task['priority']" />
                <button type="button" wire:click="toggleTimer({{ $task['id'] }})" aria-label="{{ $isRunning ? 'Zastaviť čas' : 'Spustiť čas' }}"
                    @class(['flex size-9 shrink-0 items-center justify-center rounded-full', 'bg-emerald-600 text-white' => $isRunning, 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $isRunning])>
                    @if ($isRunning)
                        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2" /></svg>
                    @else
                        <svg class="ms-0.5 size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 001.5.86l11-6.86a1 1 0 000-1.72l-11-6.86A1 1 0 008 5.14z" /></svg>
                    @endif
                </button>
            </li>
        @empty
            @unless ($error)
                <li class="rounded-2xl bg-white px-4 py-8 text-center text-sm text-zinc-500 dark:bg-zinc-900">Nič urgentné. 🎉</li>
            @endunless
        @endforelse
    </ul>

    @if ($conflict)
        <div class="fixed inset-0 z-40 flex items-end bg-black/40" wire:click.self="cancelConflict">
            <div class="w-full rounded-t-3xl bg-white p-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)] dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">{{ $conflict['message'] }}</h2>
                <p class="mt-1 text-sm text-zinc-500">Ak zrušíš, nová úloha zostane v backlogu.</p>
                <div class="mt-4 space-y-2">
                    @foreach ($conflict['occupants'] as $occupant)
                        <label wire:key="occ-{{ $occupant['id'] }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 has-checked:border-emerald-500 dark:border-zinc-700">
                            <input type="radio" wire:model="demoteId" value="{{ $occupant['id'] }}" class="size-5 accent-emerald-600">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ $occupant['title'] }}</span>
                                @if ($occupant['project']) <span class="block text-xs text-zinc-500">{{ $occupant['project']['company'] }} · {{ $occupant['project']['name'] }}</span> @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="cancelConflict" class="rounded-xl bg-zinc-100 py-3 font-semibold dark:bg-zinc-800">Zrušiť</button>
                    <button type="button" wire:click="resolveConflict" class="rounded-xl bg-emerald-600 py-3 font-semibold text-white disabled:opacity-50" @disabled(! $demoteId) x-bind:disabled="! $wire.demoteId">Posunúť</button>
                </div>
            </div>
        </div>
    @endif
</div>
