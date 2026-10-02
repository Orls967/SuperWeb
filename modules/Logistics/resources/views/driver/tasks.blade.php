<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Tugas Driver</h2>
            <p class="text-xs text-slate-400">{{ $driver->driver_number }} &bull; {{ $driver->license_class }} &bull; SIM s/d {{ $driver->license_expiry->format('d/m/Y') }}</p>
        </div>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto px-4 space-y-5">
        @if(session('success'))
            <div class="p-3 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="p-3 rounded-xl bg-amber-950/80 border border-amber-500/50 text-amber-200 text-sm">{{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="p-3 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Scan pickup --}}
        <form method="POST" action="{{ route('logistics.driver.pickup') }}" class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 space-y-2">
            @csrf
            <label for="tracking_number" class="text-xs font-semibold text-slate-300">Scan / ketik nomor resi saat pickup</label>
            <div class="flex gap-2">
                <input id="tracking_number" name="tracking_number" required autocomplete="off" inputmode="text" placeholder="SRX..." class="flex-1 min-w-0 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
                <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold">Pickup</button>
            </div>
        </form>

        {{-- Trip --}}
        @if($trips->isNotEmpty())
            <section class="space-y-2">
                <h3 class="text-sm font-bold text-slate-100">Trip Hari Ini</h3>
                @foreach($trips as $trip)
                    <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300">
                        <div class="font-mono text-amber-300 font-semibold">{{ $trip->schedule_number }}</div>
                        <div>{{ $trip->origin?->name }} &rarr; {{ $trip->destination?->name }}</div>
                        <div class="text-slate-400">ETD {{ $trip->etd->format('d/m H:i') }} &bull; ETA {{ $trip->eta->format('d/m H:i') }} &bull; {{ $trip->asset?->plate_number }}</div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Pickup stops --}}
        <section class="space-y-2" id="pickups">
            <h3 class="text-sm font-bold text-slate-100">Stop Pickup ({{ $pickups->count() }})</h3>
            @forelse($pickups as $s)
                <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300">
                    <div class="font-mono text-amber-300 font-semibold">{{ $s->tracking_number }}</div>
                    <div>{{ $s->origin?->name }} ({{ $s->origin?->city }})</div>
                </div>
            @empty
                <div class="p-4 text-xs text-slate-400">Tidak ada stop pickup.</div>
            @endforelse
        </section>

        {{-- Ready for delivery --}}
        <section class="space-y-2" id="ready">
            <h3 class="text-sm font-bold text-slate-100">Siap Diantar ({{ $readyForDelivery->count() }})</h3>
            @forelse($readyForDelivery as $s)
                <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 space-y-2">
                    <div class="font-mono text-amber-300 font-semibold">{{ $s->tracking_number }}</div>
                    <div>{{ $s->consignee_name }} &bull; {{ $s->consignee_phone }}</div>
                    <div class="text-slate-400">{{ $s->consignee_address['street'] ?? '' }}, {{ $s->consignee_address['city'] ?? $s->destination?->city }}</div>
                    <form method="POST" action="{{ route('logistics.driver.start-delivery', $s->id) }}">
                        @csrf
                        <button class="w-full px-4 py-3 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-bold">Mulai Antar</button>
                    </form>
                </div>
            @empty
                <div class="p-4 text-xs text-slate-400">Tidak ada paket siap antar.</div>
            @endforelse
        </section>

        {{-- Delivering --}}
        <section class="space-y-3" id="delivering">
            <h3 class="text-sm font-bold text-slate-100">Sedang Diantar ({{ $delivering->count() }})</h3>
            @forelse($delivering as $s)
                <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60 text-xs text-slate-300 space-y-3"
                     x-data="signaturePad()" data-stop="{{ $s->tracking_number }}">
                    <div>
                        <div class="font-mono text-amber-300 font-semibold">{{ $s->tracking_number }}</div>
                        <div>{{ $s->consignee_name }} &bull; {{ $s->consignee_phone }}</div>
                        <div class="text-slate-400">{{ $s->consignee_address['street'] ?? '' }}, {{ $s->consignee_address['city'] ?? $s->destination?->city }}</div>
                        <div class="text-slate-500 mt-1">Percobaan gagal: {{ $s->failed_delivery_attempts }} / {{ \Modules\Logistics\Application\Actions\ReportFailedDeliveryAction::MAX_ATTEMPTS }}</div>
                    </div>

                    <form method="POST" action="{{ route('logistics.driver.deliver', $s->id) }}" enctype="multipart/form-data" class="space-y-2" @submit="prepare($event)">
                        @csrf
                        <input name="receiver_name" required maxlength="100" placeholder="Nama penerima" class="w-full bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm">
                        <input name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="OTP 6 digit dari penerima" class="w-full bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-sm tracking-widest">
                        <input type="file" name="photo" required accept="image/*" capture="environment" class="w-full text-xs text-slate-300">
                        <div>
                            <div class="text-[11px] text-slate-400 mb-1">Tanda tangan penerima</div>
                            <canvas x-ref="canvas" width="600" height="220" class="w-full h-32 bg-white rounded-lg touch-none"
                                    @pointerdown="start($event)" @pointermove="draw($event)" @pointerup="stop()" @pointerleave="stop()"></canvas>
                            <button type="button" @click="clear()" class="mt-1 text-[11px] text-slate-400 underline">Hapus tanda tangan</button>
                        </div>
                        @if($s->cod_amount_idr > 0)
                            <label class="flex items-start gap-2 p-3 rounded-lg bg-amber-950/60 border border-amber-700 text-amber-200">
                                <input type="checkbox" name="cod_collected" value="1" required class="mt-0.5">
                                <span>COD: saya telah menerima uang tunai <strong>Rp {{ number_format($s->cod_amount_idr, 0, ',', '.') }}</strong> dari penerima.</span>
                            </label>
                        @endif
                        <input type="hidden" name="signature" x-ref="signature">
                        <button class="w-full px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold">Konfirmasi Terkirim</button>
                    </form>

                    <div class="grid grid-cols-2 gap-2">
                        <form method="POST" action="{{ route('logistics.driver.start-delivery', $s->id) }}">
                            @csrf
                            <button class="w-full px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 text-xs font-semibold">Kirim Ulang OTP</button>
                        </form>
                        <details class="col-span-2 sm:col-span-1">
                            <summary class="cursor-pointer px-3 py-2 rounded-lg bg-rose-900/60 text-rose-200 text-xs font-semibold text-center">Lapor Gagal</summary>
                            <form method="POST" action="{{ route('logistics.driver.fail', $s->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <select name="reason" required class="w-full bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                    @foreach($failureReasons as $reason)
                                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                    @endforeach
                                </select>
                                <input name="notes" maxlength="500" placeholder="Catatan (opsional)" class="w-full bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                <button class="w-full px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold">Catat Gagal</button>
                            </form>
                        </details>
                    </div>
                </div>
            @empty
                <div class="p-4 text-xs text-slate-400">Tidak ada paket dalam pengantaran.</div>
            @endforelse
        </section>
    </div>

    <script>
        function signaturePad() {
            return {
                drawing: false,
                dirty: false,
                ctx() { return this.$refs.canvas.getContext('2d'); },
                pos(e) {
                    const r = this.$refs.canvas.getBoundingClientRect();
                    return { x: (e.clientX - r.left) * (this.$refs.canvas.width / r.width), y: (e.clientY - r.top) * (this.$refs.canvas.height / r.height) };
                },
                start(e) {
                    this.drawing = true;
                    const c = this.ctx(); const p = this.pos(e);
                    c.lineWidth = 3; c.lineCap = 'round'; c.strokeStyle = '#111827';
                    c.beginPath(); c.moveTo(p.x, p.y);
                },
                draw(e) {
                    if (!this.drawing) return;
                    const c = this.ctx(); const p = this.pos(e);
                    c.lineTo(p.x, p.y); c.stroke(); this.dirty = true;
                },
                stop() { this.drawing = false; },
                clear() { this.ctx().clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height); this.dirty = false; },
                prepare(e) {
                    if (!this.dirty) { e.preventDefault(); alert('Tanda tangan penerima wajib diisi.'); return; }
                    this.$refs.signature.value = this.$refs.canvas.toDataURL('image/png');
                },
            };
        }
    </script>
</x-app-layout>
