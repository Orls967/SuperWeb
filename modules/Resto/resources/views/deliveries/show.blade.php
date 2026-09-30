<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.deliveries.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Delivery Order #{{ $delivery->order?->number }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $delivery->outlet?->name }} &bull; {{ $delivery->created_at->format('d F Y H:i') }} WIB</p>
                </div>
            </div>

            <span class="px-3.5 py-1.5 rounded-full text-xs font-bold
                {{ $delivery->status->value === 'delivered' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $delivery->status->value === 'on_the_way' ? 'bg-blue-100 text-blue-800 animate-pulse' : '' }}
                {{ $delivery->status->value === 'preparing' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $delivery->status->value === 'failed' ? 'bg-rose-100 text-rose-800' : '' }}">
                {{ $delivery->status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalFail: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Penerima & Kontak</span>
                    <h3 class="text-base font-bold text-slate-800 mt-1">{{ $delivery->recipient_name }}</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $delivery->recipient_phone }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Jarak & Ongkir</span>
                    <h3 class="text-base font-bold text-slate-800 mt-1">{{ (float)$delivery->distance_km }} KM</h3>
                    <p class="text-xs text-teal-600 font-bold mt-0.5">Ongkir: Rp {{ number_format($delivery->delivery_fee, 0, ',', '.') }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Informasi Kurir</span>
                    <h3 class="text-base font-bold text-slate-800 mt-1">{{ $delivery->courier_name ?: 'Belum ditugaskan' }}</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $delivery->tracking_number ?: 'Menunggu nomor resi / tracking' }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pembayaran</span>
                    <h3 class="text-xl font-black text-slate-900 mt-1">Rp {{ number_format($delivery->order?->grand_total, 0, ',', '.') }}</h3>
                    <p class="text-xs text-emerald-600 font-bold mt-0.5">Metode: {{ strtoupper($delivery->order?->payment_method ?? 'WALLET') }}</p>
                </div>
            </div>

            <!-- Address Card -->
            <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Alamat Pengantaran</span>
                <p class="text-sm text-slate-800 font-medium">{{ $delivery->delivery_address }}</p>
                @if($delivery->status->value === 'failed')
                    <div class="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700">
                        <span class="font-bold">Alasan Kegagalan Pengantaran:</span> {{ $delivery->failure_reason }}
                    </div>
                @endif
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Daftar Hidangan yang Dipesan</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Rincian menu dan kemasan</p>
                    </div>

                    <!-- Operational Buttons -->
                    <div class="flex items-center gap-2">
                        @if($delivery->status->value === 'preparing')
                            <form method="POST" action="{{ route('resto.deliveries.status', $delivery) }}">
                                @csrf
                                <input type="hidden" name="status" value="on_the_way">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    <span>Kirimkan ke Alamat</span>
                                </button>
                            </form>
                        @elseif($delivery->status->value === 'on_the_way')
                            <form method="POST" action="{{ route('resto.deliveries.status', $delivery) }}">
                                @csrf
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Konfirmasi Makanan Diterima</span>
                                </button>
                            </form>
                            <button type="button" @click="modalFail = true" class="px-4 py-2 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-xl text-xs font-bold transition-all">
                                Tandai Gagal Kirim
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Menu Makanan</th>
                                <th class="p-4 text-center">Porsi</th>
                                <th class="p-4 text-right">Harga Bungkus</th>
                                <th class="p-4 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($delivery->order?->items ?? [] as $item)
                                <tr>
                                    <td class="p-4 font-bold text-slate-800">{{ $item->name_snapshot }}</td>
                                    <td class="p-4 text-center font-bold text-slate-700">{{ $item->qty }}</td>
                                    <td class="p-4 text-right font-mono text-slate-600">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="p-4 text-right font-bold text-slate-900">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-xs text-slate-600 font-semibold">
                            <tr>
                                <td colspan="3" class="p-3 text-right">Subtotal Makanan:</td>
                                <td class="p-3 text-right font-mono font-bold text-slate-800">Rp {{ number_format($delivery->order?->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="p-3 text-right">Pajak Restoran PB1 (10%):</td>
                                <td class="p-3 text-right font-mono">Rp {{ number_format($delivery->order?->tax_pb1, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="p-3 text-right">Biaya Kemasan Nasi Bungkus:</td>
                                <td class="p-3 text-right font-mono">Rp {{ number_format($delivery->packaging_fee, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="p-3 text-right">Ongkos Kirim ({{ (float)$delivery->distance_km }} km):</td>
                                <td class="p-3 text-right font-mono text-teal-600 font-bold">Rp {{ number_format($delivery->delivery_fee, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="border-t-2 border-slate-300 text-sm font-black text-slate-900">
                                <td colspan="3" class="p-4 text-right uppercase tracking-wider">Total Tagihan:</td>
                                <td class="p-4 text-right text-base text-emerald-600">Rp {{ number_format($delivery->order?->grand_total, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- MODAL: GAGAL KIRIM -->
            <div x-show="modalFail" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Tandai Gagal Kirim & Refund</h3>
                        <button type="button" @click="modalFail = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.deliveries.fail', $delivery) }}" class="space-y-4">
                        @csrf
                        <p class="text-xs text-slate-500">
                            Sesuai SOP, pelanggan akan mendapatkan <strong>refund harga makanan + pajak</strong> ke saldo dompet, sementara ongkos kirim hangus sebagai kompensasi kurir.
                        </p>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan Pengiriman Gagal</label>
                            <input type="text" name="reason" placeholder="cth: Alamat tidak ditemukan / penerima tidak merespon" required class="w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500 text-sm">
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalFail = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Konfirmasi Gagal & Refund</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
