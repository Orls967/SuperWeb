@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="barRevpatDashboard()">
    {{-- P-UI 5: Toast Notifications --}}
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

    {{-- Header --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Bar Supply Chain & Cukai MMEA
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        PMK 160/2023 Integrated
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Manajemen Stok Bar, Spillage & Audit Shift
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Pengawasan botol spirit bernilai tinggi, pelacakan tumpahan/spillage ml, dan audit varians antar-shift (D90-2).
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="openAuditModal = true" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-sm shadow-lg shadow-amber-500/20 transition">
                    📋 Audit Shift Bar
                </button>
            </div>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">✕</button>
            </div>
        @endif

        {{-- Bar Inventory Items Table --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Inventaris Spirit & Minuman Premium</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $items->total() }} Produk
                    </span>
                </h3>
            </div>

            {{-- P-UI 2: Empty State --}}
            @if($items->isEmpty())
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500 border border-slate-700">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-300">Belum Ada Inventaris Botol Terdaftar</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        Daftar inventaris botol spirit dan champagne premium akan muncul di sini setelah seeder atau penerimaan barang.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">SKU & Merek</th>
                                <th scope="col" class="py-3 px-4">Kategori</th>
                                <th scope="col" class="py-3 px-4">Gol. Cukai</th>
                                <th scope="col" class="py-3 px-4">Stok Saat Ini</th>
                                <th scope="col" class="py-3 px-4">Harga Beli Landed</th>
                                <th scope="col" class="py-3 px-4">Harga Jual Botol</th>
                                <th scope="col" class="py-3 px-4 text-center">Status Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($items as $it)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-white">{{ $it->brand_name }}</div>
                                        <div class="text-xs font-mono text-slate-400">{{ $it->sku }} ({{ $it->unit_volume_ml }}ml)</div>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-slate-300">
                                        {{ str_replace('_', ' ', ucfirst($it->category)) }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            Gol. {{ $it->alcohol_tier }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-mono font-bold text-lg {{ $it->current_stock_bottles <= $it->safety_stock_bottles ? 'text-rose-400' : 'text-emerald-400' }}">
                                            {{ $it->current_stock_bottles }}
                                        </span>
                                        <span class="text-xs text-slate-400">botol</span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs text-slate-300">
                                        IDR {{ number_format($it->cost_per_bottle_idr) }}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-white">
                                        IDR {{ number_format($it->selling_price_per_bottle_idr) }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if($it->current_stock_bottles <= $it->safety_stock_bottles)
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                                Low Stock
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                                Aman
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $items->links() }}
                </div>
            @endif
        </div>

        {{-- Shift Logs & Variance Reconciliation Table --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Log Shift Bar & Rekonsiliasi Tumpahan / Varians</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $shiftLogs->total() }} Shift
                    </span>
                </h3>
            </div>

            @if($shiftLogs->isEmpty())
                <div class="text-center py-12 px-4 text-slate-500 text-sm">
                    Belum ada rekaman shift bar yang ditutup untuk venue ini.
                </div>
            @else
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">Kode Shift</th>
                                <th scope="col" class="py-3 px-4">Zona Bar</th>
                                <th scope="col" class="py-3 px-4">Waktu Mulai</th>
                                <th scope="col" class="py-3 px-4">Opening</th>
                                <th scope="col" class="py-3 px-4">Penjualan POS</th>
                                <th scope="col" class="py-3 px-4">Spillage (ml)</th>
                                <th scope="col" class="py-3 px-4">Closing Aktual</th>
                                <th scope="col" class="py-3 px-4">Varians</th>
                                <th scope="col" class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($shiftLogs as $log)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-amber-300">
                                        {{ $log->shift_code }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-300">
                                        {{ $log->zone?->name ?? 'Main Bar' }}
                                    </td>
                                    <td class="py-3 px-4 text-xs font-mono text-slate-400">
                                        {{ $log->start_time?->format('H:i') ?? 'N/A' }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-300">
                                        {{ $log->opening_bottles }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-cyan-400 font-bold">
                                        {{ $log->pos_sales_bottles }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-rose-400">
                                        {{ $log->spillage_ml }} ml
                                    </td>
                                    <td class="py-3 px-4 font-mono text-white font-bold">
                                        {{ $log->closing_bottles }}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold {{ $log->variance_bottles < 0 ? 'text-rose-400' : ($log->variance_bottles > 0 ? 'text-amber-400' : 'text-emerald-400') }}">
                                        {{ $log->variance_bottles > 0 ? '+'.$log->variance_bottles : $log->variance_bottles }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $log->status === 'reconciled' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30' }}">
                                            ● {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $shiftLogs->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Kepatuhan Cukai MMEA (PMK 160/2023) & Protokol Anti-Spillage (D90-2) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Box 1: Tarif & Verifikasi Pita Cukai MMEA --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Verifikasi Cukai MMEA & Pita Cukai</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300 font-semibold">PMK 160/2023</span>
                </div>
                <p class="text-xs text-slate-400">
                    Pencatatan tarif cukai spesifik per liter impor dan kuotasi landed cost terintegrasi langsung dengan modul Trade (G7-K19).
                </p>

                <div class="space-y-2 pt-1 text-xs">
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Golongan A (Kadar s/d 5%):</span>
                            <div class="text-[10px] text-slate-400">Bier impor, cider, dan hard seltzer</div>
                        </div>
                        <span class="font-mono text-amber-400 font-bold">IDR 16.500 / liter</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Golongan B (Kadar > 5% s/d 20%):</span>
                            <div class="text-[10px] text-slate-400">Wine, sake, soju, dan vermouth</div>
                        </div>
                        <span class="font-mono text-cyan-400 font-bold">IDR 42.500 / liter</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Golongan C (Kadar > 20% s/d 55%):</span>
                            <div class="text-[10px] text-slate-400">Whisky, gin, vodka, rum, tequila, cognac</div>
                        </div>
                        <span class="font-mono text-purple-400 font-bold">IDR 150.000 / liter</span>
                    </div>
                </div>
            </div>

            {{-- Box 2: Protokol Pengendalian Spillage & Pour Spout Telemetry --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Pengendalian Spillage & Pour Spouts</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs bg-rose-500/20 text-rose-300 font-semibold">Toleransi 2.5%</span>
                </div>
                <p class="text-xs text-slate-400">
                    Sensor takaran nirkabel (*smart pour spout*) mengirim data telemetri mililiter per penuangan cocktail secara real-time.
                </p>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Standar Porsi Tunggal (Single Shot):</span>
                        <span class="font-bold text-white">45 ml (1.5 oz)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Toleransi Varians Tumpahan per Shift:</span>
                        <span class="font-bold text-emerald-400">Maksimal 350 ml / stasiun</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Status Audit Timbangan Botol:</span>
                        <span class="font-bold text-cyan-400">Terkalibrasi Presisi 0.1 gram</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700 text-slate-400">
                        *Bila terjadi kelebihan tumpahan (*excess spillage*) melebihi ambang 500 ml pada satu shift, sistem memblokir otorisasi pembukaan botol baru hingga supervisor bar melakukan approval audit fisik.
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Integrasi Resep Koktail POS & Pemotongan Otomatis Stok Botol (D90-2) --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Resep Koktail POS & Pemotongan Otomatis Takaran Cairan</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-purple-500/20 text-purple-300 font-semibold">BOM Bartending</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Setiap transaksi koktail di POS kasir memicu pengurangan mililiter bahan baku spirit dan liqueur secara atomik.
                    </p>
                </div>
                <span class="text-xs font-mono text-emerald-400">Status POS Sync: 100% Real-Time</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-amber-400 uppercase">Atlas Sunset Negroni</span>
                    <p class="text-slate-400">Komposisi: 30ml London Dry Gin, 30ml Campari Bitter, 30ml Sweet Vermouth Rosso.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">COGS per Gelas:</span>
                        <span class="text-white font-bold">IDR 38.500 (Jual IDR 185.000)</span>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-cyan-400 uppercase">Berawa Coconut Margarita</span>
                    <p class="text-slate-400">Komposisi: 45ml Blanco Tequila 100% Agave, 20ml Triple Sec, 30ml Fresh Lime, 15ml Coconut Puree.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">COGS per Gelas:</span>
                        <span class="text-white font-bold">IDR 42.000 (Jual IDR 195.000)</span>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-purple-400 uppercase">Smoked Espresso Martini</span>
                    <p class="text-slate-400">Komposisi: 45ml Premium Vodka, 30ml Kahlua Coffee Liqueur, 30ml Fresh Espresso Roast, 10ml Vanilla.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">COGS per Gelas:</span>
                        <span class="text-white font-bold">IDR 44.500 (Jual IDR 210.000)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Modal Audit Shift Bar --}}
    <div x-show="openAuditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openAuditModal = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Formulir Audit Shift Bar</h3>
                        <p class="text-xs text-slate-400">Verifikasi fisik botol akhir shift dan rekonsiliasi tumpahan.</p>
                    </div>
                    <button type="button" @click="openAuditModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form method="POST" action="{{ route('venue.economy.bar.audit') }}" class="space-y-4 py-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Pilih ID Shift Log</label>
                        <input type="number" name="shift_log_id" x-model="form.shift_log_id" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Botol Closing Fisik (Aktual)</label>
                            <input type="number" name="actual_closing_bottles" x-model="form.closing_bottles" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Ukuran Standar Botol (ml)</label>
                            <input type="number" name="standard_bottle_volume_ml" value="750" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="openAuditModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-xs transition">
                            Tutup & Reconcile Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function barRevpatDashboard() {
    return {
        openAuditModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        form: {
            shift_log_id: 1,
            closing_bottles: 42
        }
    };
}
</script>
@endsection
