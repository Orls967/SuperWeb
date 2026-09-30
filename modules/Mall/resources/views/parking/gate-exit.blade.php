<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">Gate Keluar Parkir</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Scan tiket, hitung tarif progresif, terima pembayaran, lalu buka palang
                </p>
            </div>
            <a href="{{ route('mall.parking.gate.entry', ['property_id' => $property?->id]) }}"
               class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-md transition-all">
                Pindah ke Gate Masuk
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Pencarian tiket --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <form method="GET" action="{{ route('mall.parking.gate.exit') }}" class="flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="property_id" value="{{ $property?->id }}">
                    <input type="text" name="ticket" value="{{ $ticket }}" autofocus
                           placeholder="Scan atau ketik nomor tiket (TKT-…)"
                           class="flex-1 rounded-xl border-slate-300 text-base font-mono focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-500/20 transition-all">
                        Cari Tiket
                    </button>
                </form>

                @if($lookupError)
                    <p class="mt-3 text-sm text-rose-600 font-semibold">{{ $lookupError }}</p>
                @endif
            </div>

            @if($session && $quote)
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                     x-data="parkingGate({ totalFee: {{ $session->total_fee > 0 ? $session->total_fee : $quote['total_fee'] }} })">

                    {{-- Rincian tiket --}}
                    <div class="px-6 py-5 bg-slate-50 border-b border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-1">Tiket</p>
                            <p class="font-mono text-sm font-bold text-slate-800">{{ $session->ticket_number }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-1">Plat</p>
                            <p class="font-mono text-sm font-bold text-slate-800">{{ $session->plate_number }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-1">Masuk</p>
                            <p class="text-sm text-slate-700">{{ $session->entry_time->format('d/m H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-1">Durasi</p>
                            <p class="text-sm text-slate-700">
                                {{ intdiv($quote['duration_minutes'], 60) }}j {{ $quote['duration_minutes'] % 60 }}m
                                <span class="text-slate-400">({{ $quote['billed_hours'] }} jam ditagih)</span>
                            </p>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        {{-- Rincian tarif --}}
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Tarif dasar</span>
                                <span class="font-mono text-slate-800">Rp {{ number_format($quote['base_fee'], 0, ',', '.') }}</span>
                            </div>

                            @if($quote['penalty_fee'] > 0)
                                <div class="flex justify-between text-rose-600">
                                    <span>Denda tiket hilang</span>
                                    <span class="font-mono">+ Rp {{ number_format($quote['penalty_fee'], 0, ',', '.') }}</span>
                                </div>
                            @endif

                            @if($quote['discount_amount'] > 0)
                                <div class="flex justify-between text-emerald-600">
                                    <span>
                                        Validasi {{ $session->validation_free_hours }} jam
                                        oleh {{ $session->validatedByTenant?->brand_name ?? 'tenant' }}
                                    </span>
                                    <span class="font-mono">− Rp {{ number_format($quote['discount_amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif

                            @if($session->member)
                                <div class="flex justify-between text-blue-600">
                                    <span>Langganan member {{ $session->member->member_number }}</span>
                                    <span class="font-bold">BEBAS BIAYA</span>
                                </div>
                            @endif

                            <div class="flex justify-between pt-3 border-t border-slate-200">
                                <span class="font-bold text-slate-800">Total dibayar</span>
                                <span class="font-mono text-xl font-black text-slate-900">
                                    Rp {{ number_format($quote['total_fee'], 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        @if($session->exit_time === null)
                            {{-- Tahap 1: finalisasi tarif --}}
                            <form action="{{ route('mall.parking.gate.check-out') }}" method="POST">
                                @csrf
                                <input type="hidden" name="ticket_number" value="{{ $session->ticket_number }}">
                                <input type="hidden" name="exit_gate" value="Gate Keluar 1">
                                <button type="submit"
                                        class="w-full py-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold transition-all">
                                    {{ $quote['total_fee'] > 0 ? 'Kunci Tarif & Lanjut ke Pembayaran' : 'Buka Palang (Bebas Biaya)' }}
                                </button>
                            </form>
                        @elseif($session->total_fee > 0 && ! $session->isPaid())
                            {{-- Tahap 2: terima pembayaran --}}
                            <form action="{{ route('mall.parking.gate.settle', $session->ticket_number) }}" method="POST" class="space-y-4"
                                  @submit="submitting = true">
                                @csrf
                                <input type="hidden" name="idempotency_key" :value="idempotencyKey">

                                <div class="flex gap-2">
                                    <button type="button" @click="paymentMethod = 'cash'"
                                            :class="paymentMethod === 'cash' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300'"
                                            class="flex-1 py-2.5 rounded-xl border text-xs font-bold transition-all">
                                        Tunai
                                    </button>
                                    <button type="button" @click="paymentMethod = 'wallet'"
                                            :class="paymentMethod === 'wallet' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300'"
                                            class="flex-1 py-2.5 rounded-xl border text-xs font-bold transition-all">
                                        Dompet Digital
                                    </button>
                                </div>
                                <input type="hidden" name="payment_method" :value="paymentMethod">

                                {{-- Tunai --}}
                                <div x-show="paymentMethod === 'cash'" class="space-y-3">
                                    <label class="block text-xs font-bold text-slate-600">Uang Diterima</label>
                                    <input type="number" name="cash_tendered" x-model="cashTendered" min="0" step="1000"
                                           class="w-full rounded-xl border-slate-300 text-lg font-mono focus:border-blue-500 focus:ring-blue-500">

                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="exactTender()"
                                                class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-600">
                                            Uang Pas
                                        </button>
                                        @foreach([5000, 10000, 20000, 50000, 100000] as $nominal)
                                            <button type="button" @click="quickTender({{ $nominal }})"
                                                    class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-600">
                                                {{ number_format($nominal / 1000) }}rb
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="flex justify-between text-sm p-3 rounded-xl bg-slate-50">
                                        <span class="text-slate-500">Kembalian</span>
                                        <span class="font-mono font-bold text-slate-800" x-text="formatIdr(change)"></span>
                                    </div>

                                    <p x-show="cashShortfall > 0" class="text-xs text-rose-600 font-semibold">
                                        Uang kurang <span x-text="formatIdr(cashShortfall)"></span>
                                    </p>

                                    @error('cash_tendered')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>

                                {{-- Dompet --}}
                                <div x-show="paymentMethod === 'wallet'" class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Email Pemilik Dompet</label>
                                        <input type="email" name="payer_email" value="{{ old('payer_email') }}"
                                               class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        @error('payer_email')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 mb-1.5">PIN Dompet (6 digit)</label>
                                        <input type="password" name="pin" inputmode="numeric" maxlength="6"
                                               class="w-full rounded-xl border-slate-300 text-lg font-mono tracking-[0.5em] focus:border-blue-500 focus:ring-blue-500">
                                        @error('pin')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <button type="submit" :disabled="!canSubmit"
                                        :class="canSubmit ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-slate-300 cursor-not-allowed'"
                                        class="w-full py-3 text-white rounded-xl text-sm font-bold shadow-md transition-all">
                                    <span x-show="!submitting">Terima Pembayaran & Buka Palang</span>
                                    <span x-show="submitting" x-cloak>Memproses…</span>
                                </button>
                            </form>
                        @else
                            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-bold text-center">
                                Tiket sudah selesai. Palang keluar dibuka
                                {{ $session->paid_at?->format('d/m/Y H:i') }}.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
