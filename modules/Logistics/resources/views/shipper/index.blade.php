<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-white tracking-tight flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-gradient-to-tr from-sky-500/20 to-blue-500/20 border border-sky-500/30 text-sky-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </span>
                    Portal Pengiriman (Shipper Portal)
                </h2>
                <p class="text-sm text-slate-400 mt-1">Kelola pemesanan kargo, pelacakan resi, dan riwayat kiriman Sari Ranah Express</p>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="{{ route('logistics.shipments.bulk') }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition shadow-sm">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    Upload CSV Massal
                </a>
                <a href="{{ route('logistics.shipments.create') }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white text-sm font-semibold shadow-lg shadow-sky-600/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Kirim Kargo Baru
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-900/40 border border-emerald-500/40 text-emerald-300 text-sm flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <!-- Account Banner if B2B -->
            @if($account)
                <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border border-slate-800 shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/50 text-emerald-400 border border-emerald-700/50 mb-2">
                            Akun B2B Pascabayar Aktif
                        </span>
                        <h3 class="text-lg font-bold text-white">{{ auth()->user()->name }}</h3>
                        <p class="text-xs text-slate-400">Termin Pembayaran: Net {{ $account->payment_terms_days }} Hari</p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div>
                            <div class="text-xs text-slate-400">Batas Kredit (Limit)</div>
                            <div class="text-lg font-mono font-bold text-white">Rp {{ number_format($account->credit_limit_idr, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400">Pemakaian Berjalan</div>
                            <div class="text-lg font-mono font-bold text-amber-400">Rp {{ number_format($account->calculateOutstandingBalance(), 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filter & Search Bar -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 backdrop-blur-md shadow-lg flex flex-col sm:flex-row gap-3">
                <form method="GET" action="{{ route('logistics.shipments.index') }}" class="flex-1 flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor resi (SRX...) atau nama penerima..." class="w-full pl-10 pr-4 py-2 bg-slate-950/60 border border-slate-700/80 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <svg class="w-4 h-4 text-slate-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <select name="status" onchange="this.form.submit()" class="px-3 py-2 bg-slate-950/60 border border-slate-700/80 rounded-xl text-sm text-slate-300 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        @foreach(\Modules\Logistics\Domain\Enums\ShipmentStatus::cases() as $st)
                            <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>{{ $st->label() }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-xl transition">
                        Filter
                    </button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('logistics.shipments.index') }}" class="px-3 py-2 text-slate-400 hover:text-white text-sm flex items-center justify-center">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            <!-- Shipments Table / Card List -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl backdrop-blur-md">
                @if($shipments->isEmpty())
                    <div class="py-16 text-center">
                        <svg class="w-16 h-16 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        <h4 class="text-base font-semibold text-slate-300">Belum ada data pengiriman</h4>
                        <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">Mulai kirimkan kargo dan paket Anda melalui jaringan logistik terpadu Sari Ranah Express.</p>
                        <a href="{{ route('logistics.shipments.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-sm font-semibold transition">
                            Buat Pengiriman Pertama
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="py-3.5 px-4 font-semibold">Nomor Resi</th>
                                    <th class="py-3.5 px-4 font-semibold">Rute & Moda</th>
                                    <th class="py-3.5 px-4 font-semibold">Penerima</th>
                                    <th class="py-3.5 px-4 font-semibold">Berat / Biaya</th>
                                    <th class="py-3.5 px-4 font-semibold">Status</th>
                                    <th class="py-3.5 px-4 font-semibold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @foreach($shipments as $shipment)
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="py-4 px-4">
                                            <div class="font-mono font-bold text-sky-400 flex items-center gap-1.5">
                                                <a href="{{ route('logistics.shipments.show', $shipment->id) }}" class="hover:underline">
                                                    {{ $shipment->tracking_number }}
                                                </a>
                                            </div>
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                {{ $shipment->created_at->format('d M Y, H:i') }}
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center gap-1.5 font-medium text-slate-200">
                                                <span>{{ $shipment->origin?->city }}</span>
                                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                <span>{{ $shipment->destination?->city }}</span>
                                            </div>
                                            <div class="text-xs text-slate-400 mt-0.5">
                                                {{ $shipment->service_level->label() }} ({{ $shipment->mode->label() }})
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-slate-200">{{ $shipment->consignee_name }}</div>
                                            <div class="text-xs text-slate-500">{{ $shipment->consignee_phone }}</div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-mono font-semibold text-white">
                                                Rp {{ number_format($shipment->total_amount_idr, 0, ',', '.') }}
                                            </div>
                                            <div class="text-xs text-slate-400">
                                                {{ number_format($shipment->total_chargeable_weight_g / 1000, 1) }} kg tertagih
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $shipment->status->badgeClass() }}">
                                                {{ $shipment->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="{{ route('logistics.shipments.label', $shipment->id) }}" target="_blank" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition title='Cetak Label Resi QR'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                </a>
                                                <a href="{{ route('logistics.shipments.show', $shipment->id) }}" class="p-2 rounded-lg bg-sky-900/40 hover:bg-sky-800/60 text-sky-400 hover:text-sky-300 transition title='Lihat Detail'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($shipments->hasPages())
                        <div class="p-4 border-t border-slate-800 bg-slate-950/40">
                            {{ $shipments->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
