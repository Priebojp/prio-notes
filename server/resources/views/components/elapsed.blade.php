@props(['since', 'offset' => 0])

{{-- Live h:mm:ss counter; $offset adds already tracked seconds. --}}
<span {{ $attributes->class('tabular-nums') }} wire:ignore
    x-data="{
        start: {{ $since->getTimestamp() }} - {{ (int) $offset }},
        now: Math.floor(Date.now() / 1000),
        get label() {
            const s = Math.max(0, this.now - this.start);
            const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
            return h + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        },
        init() { this.timer = setInterval(() => this.now = Math.floor(Date.now() / 1000), 1000) },
        destroy() { clearInterval(this.timer) },
    }"
    x-text="label"></span>
