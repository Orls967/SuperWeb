@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="resaleSellApp()">
    {{-- Toast Notification --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Main Form Container --}}
    <div class="max-w-4xl mx-auto space-y-8">
        {{-- Breadcrumbs & Header --}}
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-2">
                <a href="{{ route('venue.secondary.marketplace') }}" class="hover:text-cyan-400">Marketplace Sekunder</a>
                <span>/</span>
                <span class="text-slate-200">Daftarkan Tiket Jual</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-white tracking-tight">Formulir Penjualan Tiket Resmi</h1>
                    <p class="text-sm text-slate-400 mt-1 max-w-2xl">
                        Patuhi batas plafon harga legal maksimum 120% sesuai Peraturan Perlindungan Tamu & Pencegahan Spekulasi Calo (Anti-Scalping Safe Trade Framework).
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="px-3.5 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Plafon Legal: Maks 120%
                    </div>
                    <div class="px-3.5 py-1.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold">
                        Single Transfer Only
                    </div>
                </div>
            </div>
        </div>

        {{-- Verification Status Warning --}}
        <div class="p-4 bg-slate-800/80 border border-slate-700/80 rounded-2xl flex items-start gap-3.5 text-xs text-slate-300">
            <div class="p-2 bg-cyan-500/10 rounded-lg text-cyan-400 border border-cyan-500/20 flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="space-y-1">
                <div class="font-bold text-white">Ketentuan Wajib Penjual Tiket:</div>
                <p class="leading-relaxed">
                    1. Tiket yang dapat dijual kembali hanyalah tiket yang dibeli secara langsung dari promotor dan belum pernah dialihkan ke pihak lain.<br>
                    2. Dana hasil penjualan akan dikunci dalam rekening penampungan bersama (escrow) dan dicairkan ke saldo/rekening Anda setelah barcode berhasil ditransfer.<br>
                    3. Pajak PBJT 10% dan biaya platform 8% akan dipotong otomatis dari harga penawaran.
                </p>
            </div>
        </div>

        @if(session('error'))
            <div class="p-4 bg-rose-500/20 border border-rose-500/40 rounded-xl text-rose-300 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Form Box --}}
        <form method="POST" action="{{ route('venue.secondary.sell.store') }}" @submit="handleSubmit($event)" class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
            @csrf

            {{-- 1. Pilih Tiket yang Dimiliki --}}
            <div>
                <label class="block text-sm font-bold text-slate-200 mb-2">
                    1. Pilih Tiket yang Ingin Dijual
                </label>
                @if($tickets->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($tickets as $t)
                            <label class="relative flex flex-col p-4 bg-slate-900 border rounded-xl cursor-pointer hover:border-cyan-500/50 transition-all"
                                   :class="selectedTicketId == {{ $t->id }} ? 'border-cyan-500 ring-2 ring-cyan-500/30 bg-slate-900/90' : 'border-slate-700'">
                                <input type="radio" name="ticket_id" value="{{ $t->id }}" 
                                       @change="selectTicket({{ $t->id }}, {{ $t->price_paid_idr }}, '{{ $t->ticket_number }}', '{{ $t->event->title ?? 'Event' }}')" 
                                       class="sr-only" required>
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-cyan-400">#{{ $t->ticket_number }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-semibold uppercase">Layak Resale</span>
                                </div>
                                <h4 class="font-bold text-white text-sm mt-2">{{ $t->event->title ?? 'Event Entertainment' }}</h4>
                                <div class="mt-3 pt-2 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                                    <span>Harga Beli Asli:</span>
                                    <span class="font-bold text-slate-200">Rp {{ number_format($t->price_paid_idr, 0, ',', '.') }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 bg-slate-900/60 border border-slate-800 rounded-xl text-center text-slate-400 text-sm">
                        <p>Anda belum memiliki tiket sah yang memenuhi syarat penjualan sekunder (syarat: belum pernah dipindahtangankan).</p>
                    </div>
                @endif
            </div>

            {{-- 2. Tentukan Harga Penawaran --}}
            <div x-show="selectedTicketId" class="pt-4 border-t border-slate-700/60 space-y-4">
                <label class="block text-sm font-bold text-slate-200">
                    2. Masukkan Harga Penawaran Jual (IDR)
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                    <div>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-slate-400 font-bold text-sm">Rp</span>
                            <input type="number" name="asking_price_idr" x-model.number="askingPrice" @input="recalculateWaterfall()"
                                   min="1000" :max="maxPriceCeiling" required
                                   class="w-full bg-slate-900 border rounded-xl pl-12 pr-4 py-3 text-lg font-bold text-white focus:outline-none transition-all"
                                   :class="isPriceExceeded ? 'border-rose-500 ring-2 ring-rose-500/30' : 'border-slate-700 focus:border-cyan-500'">
                        </div>
                        <template x-if="isPriceExceeded">
                            <p class="text-xs text-rose-400 mt-2 font-semibold">
                                ❌ Harga melebihi plafon 120% (Maksimum yang diizinkan: Rp <span x-text="maxPriceCeiling.toLocaleString('id-ID')"></span>).
                            </p>
                        </template>
                        <template x-if="!isPriceExceeded && askingPrice > 0">
                            <p class="text-xs text-emerald-400 mt-2 font-medium">
                                ✓ Harga sah di dalam batas plafon legal anti-scalping (Maks 120%).
                            </p>
                        </template>
                    </div>

                    <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-700/60 text-xs space-y-2">
                        <div class="flex justify-between text-slate-400">
                            <span>Harga Pokok Asli (Face Value):</span>
                            <span class="font-bold text-slate-200" x-text="'Rp ' + originalFaceValue.toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-amber-400 font-semibold">
                            <span>Plafon Maksimum Legal (120%):</span>
                            <span x-text="'Rp ' + maxPriceCeiling.toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Persentase Kenaikan:</span>
                            <span class="font-bold text-cyan-400" x-text="markupPercent + '%'"></span>
                        </div>
                        <div class="w-full bg-slate-800 h-1 rounded-full overflow-hidden mt-1">
                            <div class="bg-gradient-to-r from-emerald-500 to-amber-500 h-full" :style="'width: ' + Math.min(100, Math.max(5, (askingPrice / maxPriceCeiling) * 100)) + '%'"></div>
                        </div>
                    </div>
                </div>

                {{-- Live Waterfall Distribution Simulator --}}
                <div class="bg-slate-900/90 rounded-2xl p-6 border border-slate-700/80 space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                        <span>Rincian Pembagian Hasil Transaksi (Waterfall Settlement)</span>
                        <span class="text-cyan-400 font-mono">100% INTEGER CONSERVED</span>
                    </h4>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                        <div class="p-3.5 bg-slate-800 rounded-xl border border-slate-700/50 space-y-1">
                            <div class="text-[11px] text-slate-400">Pajak PBJT (10%)</div>
                            <div class="text-base font-bold text-indigo-300" x-text="'Rp ' + taxPbjt.toLocaleString('id-ID')"></div>
                            <div class="text-[10px] text-slate-500">Disetor ke kas daerah</div>
                        </div>

                        <div class="p-3.5 bg-slate-800 rounded-xl border border-slate-700/50 space-y-1">
                            <div class="text-[11px] text-slate-400">Escrow Fee (8%)</div>
                            <div class="text-base font-bold text-cyan-300" x-text="'Rp ' + platformFee.toLocaleString('id-ID')"></div>
                            <div class="text-[10px] text-slate-500">Operasional bursa</div>
                        </div>

                        <div class="p-3.5 bg-slate-800 rounded-xl border border-slate-700/50 space-y-1">
                            <div class="text-[11px] text-slate-400">Royalti Artis (2%)</div>
                            <div class="text-base font-bold text-amber-300" x-text="'Rp ' + promoterRoyalty.toLocaleString('id-ID')"></div>
                            <div class="text-[10px] text-slate-500">Kreator & promotor</div>
                        </div>

                        <div class="p-3.5 bg-emerald-950/40 rounded-xl border border-emerald-500/30 space-y-1">
                            <div class="text-[11px] text-emerald-400 font-bold">Penerimaan Bersih (Net)</div>
                            <div class="text-base font-black text-emerald-300" x-text="'Rp ' + sellerPayout.toLocaleString('id-ID')"></div>
                            <div class="text-[10px] text-emerald-500">Ditransfer ke penjual</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Kebijakan & Syarat --}}
            <div x-show="selectedTicketId" class="pt-4 border-t border-slate-700/60 space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="agreedToTerms" required class="mt-1 rounded bg-slate-900 border-slate-700 text-cyan-500 focus:ring-cyan-500">
                    <span class="text-xs text-slate-300 leading-relaxed">
                        Saya memahami dan menyetujui bahwa begitu tiket ini laku terjual di pasar sekunder, barcode lama pada aplikasi saya akan <strong>segera dihanguskan</strong> dan dipindahtangankan ke pembeli baru dengan token terenkripsi baru. Saya juga menyatakan bahwa tiket ini dijual secara sah dan tidak melanggar aturan single-transfer.
                    </span>
                </label>
            </div>

            {{-- Submit Action --}}
            <div x-show="selectedTicketId" class="pt-4 flex items-center justify-end gap-3">
                <a href="{{ route('venue.secondary.marketplace') }}" class="px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-semibold rounded-xl">
                    Batalkan
                </a>
                <button type="submit" :disabled="!agreedToTerms || isPriceExceeded || askingPrice <= 0"
                        class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-sm rounded-xl shadow-lg transition-all">
                    Publikasikan Listing ke Marketplace
                </button>
            </div>
        </form>

        {{-- Help Drawer --}}
        <div class="p-6 bg-slate-850/50 border border-slate-800 rounded-2xl space-y-3 text-xs text-slate-400">
            <h4 class="font-bold text-slate-200 text-sm">Pertanyaan Seputar Penjualan Tiket</h4>
            <p><strong>Kapan dana saya cair?</strong> Begitu pembeli menyelesaikan pembayaran, dana bersih akan langsung dialokasikan ke status escrow settlement dan dapat ditarik ke rekening bank terdaftar Anda.</p>
            <p><strong>Bagaimana jika tiket tidak laku?</strong> Anda dapat membatalkan listing kapan saja sebelum ada penawaran pembeli terikat. Tiket Anda akan kembali aktif untuk Anda gunakan masuk ke venue.</p>
        </div>
    </div>
</div>

<script>
function resaleSellApp() {
    return {
        selectedTicketId: null,
        originalFaceValue: 0,
        ticketNumber: '',
        eventTitle: '',
        askingPrice: 0,
        maxPriceCeiling: 0,
        markupPercent: 0,
        isPriceExceeded: false,
        agreedToTerms: false,
        taxPbjt: 0,
        platformFee: 0,
        promoterRoyalty: 0,
        sellerPayout: 0,
        toast: { visible: false, title: '', message: '', type: 'success' },

        selectTicket(id, price, number, title) {
            this.selectedTicketId = id;
            this.originalFaceValue = Number(price);
            this.ticketNumber = number;
            this.eventTitle = title;
            this.maxPriceCeiling = Math.floor(this.originalFaceValue * 1.20);
            this.askingPrice = this.originalFaceValue;
            this.recalculateWaterfall();
        },

        recalculateWaterfall() {
            if (!this.askingPrice || this.askingPrice <= 0) {
                this.taxPbjt = 0;
                this.platformFee = 0;
                this.promoterRoyalty = 0;
                this.sellerPayout = 0;
                this.markupPercent = 0;
                this.isPriceExceeded = false;
                return;
            }

            this.isPriceExceeded = this.askingPrice > this.maxPriceCeiling;
            this.markupPercent = Math.round(((this.askingPrice - this.originalFaceValue) / this.originalFaceValue) * 100);

            this.taxPbjt = Math.floor(this.askingPrice * 0.10);
            this.platformFee = Math.floor(this.askingPrice * 0.08);
            this.promoterRoyalty = Math.floor(this.askingPrice * 0.02);
            this.sellerPayout = this.askingPrice - (this.taxPbjt + this.platformFee + this.promoterRoyalty);
        },

        handleSubmit(e) {
            if (this.isPriceExceeded) {
                e.preventDefault();
                this.toast = {
                    visible: true,
                    title: 'Harga Ditolak',
                    message: 'Harga penawaran melebihi batas legal 120%. Silakan turunkan nominal jual.',
                    type: 'error'
                };
            }
        }
    };
}
</script>
@endsection

{{-- Seller Payout Destination & Historical Market Trend --}}
<div class="mt-8 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6">
    <div class="border-b border-slate-700/60 pb-3 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-white">Preferensi Rekening Pencairan Dana Escrow</h3>
            <p class="text-xs text-slate-400 mt-0.5">Pilih metode transfer dana bersih (Net Payout) setelah tiket Anda berhasil terjual.</p>
        </div>
        <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">ESCROW SECURED</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <label class="p-4 bg-slate-900 border border-cyan-500/50 rounded-xl flex items-center gap-3 cursor-pointer">
            <input type="radio" name="payout_channel" checked class="text-cyan-500 focus:ring-cyan-500">
            <div>
                <div class="font-bold text-white">Bank Transfer Kilat (BI-FAST)</div>
                <div class="text-slate-400 text-[11px] mt-0.5">BCA / Mandiri / BNI / BRI • Bebas Biaya</div>
            </div>
        </label>
        <label class="p-4 bg-slate-900 border border-slate-700 rounded-xl flex items-center gap-3 cursor-pointer hover:border-slate-600">
            <input type="radio" name="payout_channel" class="text-cyan-500 focus:ring-cyan-500">
            <div>
                <div class="font-bold text-white">E-Wallet Dompet Digital</div>
                <div class="text-slate-400 text-[11px] mt-0.5">GoPay / OVO / DANA / ShopeePay</div>
            </div>
        </label>
        <label class="p-4 bg-slate-900 border border-slate-700 rounded-xl flex items-center gap-3 cursor-pointer hover:border-slate-600">
            <input type="radio" name="payout_channel" class="text-cyan-500 focus:ring-cyan-500">
            <div>
                <div class="font-bold text-white">Saldo Venue Wristband (Cashless)</div>
                <div class="text-slate-400 text-[11px] mt-0.5">Bonus 5% F&B Credit di venue</div>
            </div>
        </label>
    </div>

    {{-- Resale Price History Graph Visualization --}}
    <div class="p-5 bg-slate-900/90 rounded-xl border border-slate-700/60 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">Tren Harga Transaksi Terakhir (7 Hari)</h4>
            <span class="text-[11px] text-cyan-400 font-medium">Rata-rata: Rp 1.150.000 (+15%)</span>
        </div>
        <div class="flex items-end gap-2 h-24 pt-4 px-2 border-b border-slate-800">
            <div class="flex-1 bg-slate-700 hover:bg-cyan-500 transition-all rounded-t h-[60%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-7: 1.0M</span>
            </div>
            <div class="flex-1 bg-slate-700 hover:bg-cyan-500 transition-all rounded-t h-[65%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-6: 1.05M</span>
            </div>
            <div class="flex-1 bg-slate-700 hover:bg-cyan-500 transition-all rounded-t h-[75%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-5: 1.1M</span>
            </div>
            <div class="flex-1 bg-slate-700 hover:bg-cyan-500 transition-all rounded-t h-[80%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-4: 1.12M</span>
            </div>
            <div class="flex-1 bg-slate-700 hover:bg-cyan-500 transition-all rounded-t h-[90%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-3: 1.18M</span>
            </div>
            <div class="flex-1 bg-amber-500 hover:bg-amber-400 transition-all rounded-t h-[95%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">H-2: 1.2M (Cap)</span>
            </div>
            <div class="flex-1 bg-emerald-500 hover:bg-emerald-400 transition-all rounded-t h-[92%] relative group">
                <span class="opacity-0 group-hover:opacity-100 absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] bg-slate-950 px-1 py-0.5 rounded text-white whitespace-nowrap">Hari Ini: 1.15M</span>
            </div>
        </div>
        <div class="flex justify-between text-[10px] text-slate-500">
            <span>7 Hari Lalu</span>
            <span>3 Hari Lalu</span>
            <span class="text-emerald-400 font-bold">Hari Ini</span>
        </div>
    </div>
</div>
