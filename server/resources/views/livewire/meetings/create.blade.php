<div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Nová porada</flux:heading>
        <flux:text class="mt-1">Po uložení sa nahrávka prepíše a pripraví sa zápis s návrhmi úloh.</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:tab.group>
                <flux:tabs variant="segmented" class="w-full">
                    <flux:tab name="record" icon="microphone">Nahrať teraz</flux:tab>
                    <flux:tab name="upload" icon="arrow-up-tray">Nahrať súbor</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="record" class="pt-4">
                    {{-- Browser recorder: MediaRecorder → File → Livewire upload into $audio --}}
                    <div wire:ignore x-data="{
                            state: 'idle', seconds: 0, progress: 0, error: null, retryFile: null,
                            recorder: null, stream: null, chunks: [], timer: null,
                            get clock() {
                                const h = Math.floor(this.seconds / 3600), m = Math.floor(this.seconds % 3600 / 60), s = this.seconds % 60;
                                return (h ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                            },
                            async start() {
                                this.error = null;
                                try {
                                    this.stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
                                } catch (e) {
                                    this.error = 'Mikrofón nie je dostupný: ' + e.message;
                                    return;
                                }
                                const mimeType = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4'].find(t => MediaRecorder.isTypeSupported(t)) || '';
                                this.recorder = new MediaRecorder(this.stream, { mimeType, audioBitsPerSecond: 48000 });
                                this.chunks = [];
                                this.seconds = 0;
                                this.recorder.ondataavailable = e => e.data.size && this.chunks.push(e.data);
                                this.recorder.onstop = () => this.upload();
                                this.recorder.start(10000);
                                this.state = 'recording';
                                this.tick();
                            },
                            tick() { clearInterval(this.timer); this.timer = setInterval(() => this.seconds++, 1000) },
                            pause() { this.recorder.pause(); clearInterval(this.timer); this.state = 'paused' },
                            resume() { this.recorder.resume(); this.tick(); this.state = 'recording' },
                            stop() {
                                clearInterval(this.timer);
                                this.recorder.stop();
                                this.stream.getTracks().forEach(t => t.stop());
                            },
                            upload() {
                                const type = this.recorder.mimeType || 'audio/webm';
                                const ext = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm');
                                const file = new File(this.chunks, 'porada-' + Date.now() + '.' + ext, { type });
                                this.state = 'uploading';
                                $wire.upload('audio', file,
                                    () => this.state = 'done',
                                    () => { this.state = 'stopped'; this.error = 'Nahrávku sa nepodarilo odoslať. Skús to znova.'; this.retryFile = file },
                                    e => this.progress = e.detail.progress);
                            },
                            retry() { this.state = 'uploading'; $wire.upload('audio', this.retryFile, () => this.state = 'done', () => this.state = 'stopped', e => this.progress = e.detail.progress) },
                        }"
                        x-on:beforeunload.window="if (['recording', 'paused', 'uploading'].includes(state)) { $event.preventDefault(); $event.returnValue = '' }"
                        class="flex flex-col items-center gap-4 py-4">

                        <div class="font-mono text-4xl tabular-nums" x-text="clock"></div>

                        <div class="flex items-center gap-2" x-show="state === 'recording'">
                            <span class="relative flex size-3">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex size-3 rounded-full bg-red-500"></span>
                            </span>
                            <flux:text>Nahráva sa…</flux:text>
                        </div>

                        <div class="flex gap-2">
                            <flux:button x-show="state === 'idle'" variant="primary" color="red" icon="microphone" x-on:click="start">Začať nahrávať</flux:button>
                            <flux:button x-show="state === 'recording'" icon="pause" x-on:click="pause">Pauza</flux:button>
                            <flux:button x-show="state === 'paused'" icon="play" x-on:click="resume">Pokračovať</flux:button>
                            <flux:button x-show="['recording', 'paused'].includes(state)" variant="primary" icon="stop" x-on:click="stop">Ukončiť</flux:button>
                            <flux:button x-show="state === 'stopped'" icon="arrow-path" x-on:click="retry">Odoslať znova</flux:button>
                        </div>

                        <div x-show="state === 'uploading'" class="w-full max-w-xs">
                            <flux:text class="mb-1 text-center">Odosiela sa… <span x-text="progress + ' %'"></span></flux:text>
                            <div class="h-2 rounded-full bg-zinc-200 dark:bg-zinc-700"><div class="h-2 rounded-full bg-emerald-500" :style="'width:' + progress + '%'"></div></div>
                        </div>

                        <flux:callout x-show="state === 'done'" icon="check-circle" color="green" heading="Nahrávka je pripravená. Dole vyplň detaily a ulož." />
                        <flux:text x-show="error" x-text="error" class="text-red-600!"></flux:text>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="upload" class="pt-4">
                    <flux:input type="file" wire:model="audio" accept="audio/*,video/mp4,video/webm,.m4a"
                        label="Zvukový súbor" description="mp3, m4a, wav, webm, ogg… Napríklad nahrávka z diktafónu v telefóne." />
                    <div wire:loading wire:target="audio" class="mt-2"><flux:text>Nahráva sa súbor…</flux:text></div>
                </flux:tab.panel>
            </flux:tab.group>

            <flux:error name="audio" />
        </flux:card>

        <flux:input wire:model="title" label="Názov" placeholder="Nechaj prázdne, AI navrhne názov" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="projectId" label="Projekt">
                <flux:select.option value="">— bez projektu —</flux:select.option>
                @foreach ($this->projects as $project)
                    <flux:select.option :value="$project->id">{{ $project->company->name }} · {{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="language" label="Jazyk porady">
                @foreach (config('meetings.languages') as $code => $label)
                    <flux:select.option :value="$code">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:input type="datetime-local" wire:model="heldAt" label="Kedy porada prebehla" />

        <flux:checkbox wire:model="consent" label="Všetci účastníci vedia o nahrávaní a súhlasia s ním." />
        <flux:error name="consent" />

        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled" wire:target="save,audio">
                Uložiť a spracovať
            </flux:button>
        </div>
    </form>
</div>
