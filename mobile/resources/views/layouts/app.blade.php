<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Prio Notes' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased select-none dark:bg-zinc-950 dark:text-zinc-100">
    <main class="mx-auto max-w-lg px-4 pt-[max(env(safe-area-inset-top),1.25rem)] pb-[calc(env(safe-area-inset-bottom)+6rem)]">
        {{ $slot }}
    </main>

    @if (app(\App\Services\Account::class)->isSignedIn())
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <div class="mx-auto grid max-w-lg grid-cols-2">
                @foreach ([['now', 'Teraz', 'M13 10V3L4 14h7v7l9-11h-7z'], ['record', 'Porada', 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z']] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" wire:navigate
                        @class(['flex flex-col items-center gap-1 py-3 text-xs font-medium', 'text-emerald-600 dark:text-emerald-400' => request()->routeIs($route), 'text-zinc-500' => ! request()->routeIs($route)])>
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
</body>
</html>
