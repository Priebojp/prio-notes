@php($d = $digest)
<x-mail::message>
# Dobré ráno, {{ $d->user->name }}

{{ ucfirst($d->date->translatedFormat('l j. F Y')) }} · P1: {{ $d->p1Count }}{{ $d->p1Capacity ? ' / '.$d->p1Capacity : '' }}

## Na dnes

@forelse ($d->focus as $task)
{!! \App\Mail\DailyDigest::taskLine($task) !!}
@empty
Nič urgentné. Dobrý deň na veci z backlogu.
@endforelse

@if ($d->upcoming->isNotEmpty())
## Termíny na najbližší týždeň

@foreach ($d->upcoming as $task)
{!! \App\Mail\DailyDigest::taskLine($task) !!}
@endforeach
@endif

@if ($d->meetingsToReview->isNotEmpty())
## Porady čakajú na rozhodnutie

@foreach ($d->meetingsToReview as $meeting)
- [{{ $meeting->title }}]({{ route('meetings.show', $meeting) }}) — {{ $meeting->pending_items_count }} {{ $meeting->pending_items_count === 1 ? 'návrh úlohy' : ($meeting->pending_items_count < 5 ? 'návrhy úloh' : 'návrhov úloh') }}
@endforeach
@endif

@if ($d->trackedYesterdayTotal() > 0)
## Včera odpracované: {{ \App\Support\Duration::human($d->trackedYesterdayTotal()) }}

@foreach ($d->trackedYesterday as $label => $seconds)
- {{ $label }}: {{ \App\Support\Duration::human($seconds) }}
@endforeach
@endif

<x-mail::button :url="route('dashboard')">
Otvoriť Prio Notes
</x-mail::button>

<x-mail::subcopy>
Denný e-mail vypneš alebo presunieš na iný čas v [nastaveniach]({{ route('digest.edit') }}).
</x-mail::subcopy>
</x-mail::message>
