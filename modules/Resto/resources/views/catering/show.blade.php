<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.catering.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Pesanan Katering #{{ $order->number }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $order->outlet?->name }} &bull; Acara: {{ $order->event_date->format('d F Y') }} ({{ substr($order->event_time, 0, 5) }} WIB)</p>
                </div>
            </div>

            <span class="px-3.5 py-1.5 rounded-full text-xs font-bold
                {{ $order->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $order->status->value === 'confirmed' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $order->status->value === 'cooking' ? 'bg-purple-100 text-purple-800 animate-pulse' : '' }}
                {{ $order->status->value === 'quoted' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $order->status->value === 'cancelled' ? 'bg-rose-100 text-rose-800' : '' }}">
                {{ $order->status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalHold: false, modalCancel: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Stepped Payment Timeline -->
            <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-sm text-slate-400 uppercase tracking-wider mb-4">Tahapan Pembayaran Katering</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border {{ $order->status->value === 'quoted' ? 'border-amber-400 bg-amber-50/50' : 'border-slate-200 bg-slate-50' }}">
                        <span class="text-xs font-bold text-slate-500 uppercase">Tahap 1: Penawaran Harga</span>
                        <div class="text-base font-extrabold text-slate-800 mt-1">Quoted</div>
                        <p class="text-xs text-slate-500 mt-0.5">Penawaran {{ $order->pax }} porsi telah disetujui</p>
                    </div>

                    <div class="p-4 rounded-xl border {{ in_array($order->status->value, ['confirmed', 'cooking', 'delivered']) ? 'border-blue-400 bg-blue-50/50' : 'border-slate-200 bg-slate-50' }}">
                        <span class="text-xs font-bold text-slate-500 uppercase">Tahap 2: Deposit 30% (Escrow)</span>
                        <div class="text-base font-extrabold text-slate-800 mt-1">Rp {{ number_format($order->deposit_amount, 0, ',', '.') }}</div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $order->deposit_intent_id ? 'Dana tersimpan aman di rekening bersama' : 'Menunggu pembayaran deposit pelanggan' }}
                        </p>
                    </div>

                    <div class="p-4 rounded-xl border {{ $order->status->value === 'completed' ? 'border-emerald-400 bg-emerald-50/50' : 'border-slate-200 bg-slate-50' }}">
                        <span class="text-xs font-bold text-slate-500 uppercase">Tahap 3: Pelunasan 70% & Cairkan</span>
                        <div class="text-base font-extrabold text-slate-800 mt-1">Rp {{ number_format($order->grand_total - $order->deposit_amount, 0, ',', '.') }}</div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $order->status->value === 'completed' ? 'Lunas: Deposit dicairkan & saldo dompet dipotong' : 'Dilunasi saat makanan selesai dikirim' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Details & Financial Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pemesan & Kontak</span>
                    <h3 class="text-base font-bold text-slate-800 mt-1">{{ $order->customer_name }}</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $order->customer_phone }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kapasitas Pax</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1">{{ $order->pax }} Pax</h3>
                    <p class="text-xs text-amber-600 font-bold mt-0.5">{{ $order->package?->name ?: 'Paket Spesial' }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Deposit Ditahan (30%)</span>
                    <h3 class="text-xl font-bold text-teal-600 mt-1">Rp {{ number_format($order->deposit_amount, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Payment Intent: {{ $order->deposit_intent_id ? "#{$order->deposit_intent_id}" : 'Belum dibayar' }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Tagihan (100%)</span>
                    <h3 class="text-xl font-black text-slate-900 mt-1">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Ongkos logistik: Rp {{ number_format($order->delivery_fee, 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- Address & Action Bar -->
            <div class="p-6 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Alamat Acara & Lokasi Antar</span>
                    <p class="text-sm font-semibold text-slate-800">{{ $order->delivery_address }}</p>
                    @if($order->notes)
                        <p class="text-xs text-amber-800 mt-1 italic"><span class="font-bold">Catatan:</span> {{ $order->notes }}</p>
                    @endif
                    @if($order->status->value === 'cancelled')
                        <div class="mt-2 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700">
                            <span class="font-bold">Alasan Pembatalan:</span> {{ $order->cancellation_reason }}
                        </div>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    @if($order->status->value === 'quoted')
                        <button type="button" @click="modalHold = true" class="px-5 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Bayar & Tahan Deposit 30%</span>
                        </button>
                    @elseif(in_array($order->status->value, ['confirmed', 'cooking', 'delivered']))
                        <form method="POST" action="{{ route('resto.catering.complete', $order) }}" onsubmit="return confirm('Apakah Anda yakin katering sudah terkirim dan siap diselesaikan? Deposit akan dicairkan dan sisa 70% dipotong dari dompet pelanggan.')">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span>Selesaikan & Pelunasan 70%</span>
                            </button>
                        </form>
                    @endif

                    @if(!in_array($order->status->value, ['completed', 'cancelled']))
                        <button type="button" @click="modalCancel = true" class="px-4 py-2.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-xl text-xs font-bold transition-all">
                            Batalkan Katering
                        </button>
                    @endif
                </div>
            </div>

            <!-- MODAL: BAYAR DEPOSIT -->
            <div x-show="modalHold" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Konfirmasi Deposit Katering 30%</h3>
                        <button type="button" @click="modalHold = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.catering.hold-deposit', $order) }}" class="space-y-4">
                        @csrf
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800">
                            Dana sebesar <strong>Rp {{ number_format($order->deposit_amount, 0, ',', '.') }}</strong> akan ditahan (hold) di rekening bersama (escrow) dan tidak dicairkan ke resto sampai pesanan selesai diantar.
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">PIN Dompet Digital (6 Digit)</label>
                            <input type="password" maxlength="6" name="pin" placeholder="******" class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-center font-mono text-lg tracking-widest">
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalHold = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Tahan Deposit Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: BATAL KATERING -->
            <div x-show="modalCancel" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Batalkan Pesanan Katering</h3>
                        <button type="button" @click="modalCancel = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.catering.cancel', $order) }}" class="space-y-4">
                        @csrf
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                            <span class="font-bold block">Kebijakan Pembatalan Katering:</span>
                            <p>&bull; Batal &ge; H-3 acara: Deposit 30% dikembalikan penuh ke dompet pelanggan.</p>
                            <p>&bull; Batal &lt; H-3 acara: Deposit 30% hangus sebagai kompensasi bahan dapur yang telah dibeli.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan Pembatalan</label>
                            <input type="text" name="reason" placeholder="cth: Acara keluarga ditunda" required class="w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500 text-sm">
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalCancel = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Kembali</button>
                            <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Konfirmasi Batalkan</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
