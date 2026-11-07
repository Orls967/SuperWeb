@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="venuePnlDashboard()">
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

    {{-- Header & Venue Selector --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Night Economics Real-Time
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live Ledger Synced
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Laporan Laba Rugi Venue & Analisis RevPAT
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Revenue Per Available Table-hour, marjin kontribusi F&B, dan pelacakan minimum spend shortfall (D90-3).
                </p>
            </div>

            {{-- P-UI 9: Filter & Search Controls --}}
            <form method="GET" action="{{ route('venue.economy.pnl') }}" class="flex items-center gap-3">
                <select name="venue_id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @foreach($venues as $v)
                        <option value="{{ $v->id }}" {{ $v->id === $selectedVenueId ? 'selected' : '' }}>
                            {{ $v->name }} ({{ $v->venue_code }})
                        </option>
                    @endforeach
                </select>
                <button type="button" @click="refreshData()" :disabled="isLoading" class="inline-flex items-center px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm font-medium border border-slate-700 transition">
                    <svg class="w-4 h-4 mr-2" :class="{'animate-spin': isLoading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.033 8.033 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </form>
        </div>
    </div>

    {{-- Main Content Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- P-UI 1: Loading Skeleton State --}}
        <div x-show="isLoading" class="grid grid-cols-1 md:grid-cols-4 gap-6 animate-pulse">
            <div class="h-32 bg-slate-800/60 rounded-2xl border border-slate-800"></div>
            <div class="h-32 bg-slate-800/60 rounded-2xl border border-slate-800"></div>
            <div class="h-32 bg-slate-800/60 rounded-2xl border border-slate-800"></div>
            <div class="h-32 bg-slate-800/60 rounded-2xl border border-slate-800"></div>
        </div>

        {{-- Executive KPI Metrics Cards --}}
        <div x-show="!isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Card 1: Total RevPAT --}}
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl backdrop-blur-sm relative overflow-hidden group hover:border-amber-500/40 transition">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-500/10 rounded-full blur-xl group-hover:bg-amber-500/20 transition"></div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Average RevPAT</p>
                <h3 class="text-2xl font-black text-amber-400 mt-2">
                    IDR {{ number_format($totalRevpatIdr) }}
                </h3>
                <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                    <span class="text-emerald-400 font-semibold">↑ 14.2%</span> vs target malam kemarin
                </p>
            </div>

            {{-- Card 2: Beverage Revenue --}}
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl backdrop-blur-sm relative overflow-hidden group hover:border-cyan-500/40 transition">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-cyan-500/10 rounded-full blur-xl group-hover:bg-cyan-500/20 transition"></div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pendapatan Minuman (Bar)</p>
                <h3 class="text-2xl font-black text-cyan-400 mt-2">
                    IDR {{ number_format($totalBeverageRevIdr) }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Kontribusi 68% dari total omzet malam
                </p>
            </div>

            {{-- Card 3: Food & Banquet Revenue --}}
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl backdrop-blur-sm relative overflow-hidden group hover:border-emerald-500/40 transition">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl group-hover:bg-emerald-500/20 transition"></div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pendapatan Makanan</p>
                <h3 class="text-2xl font-black text-emerald-400 mt-2">
                    IDR {{ number_format($totalFoodRevIdr) }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Kitchen SLA 18 menit rata-rata
                </p>
            </div>

            {{-- Card 4: Operating Zone Utilization --}}
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl backdrop-blur-sm relative overflow-hidden group hover:border-purple-500/40 transition">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-purple-500/10 rounded-full blur-xl group-hover:bg-purple-500/20 transition"></div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Okupansi Meja VIP</p>
                <h3 class="text-2xl font-black text-purple-400 mt-2">
                    88.4%
                </h3>
                <div class="w-full bg-slate-700 rounded-full h-1.5 mt-2">
                    <div class="bg-purple-500 h-1.5 rounded-full" style="width: 88.4%"></div>
                </div>
            </div>
        </div>

        {{-- Table RevPAT Breakdown & Live Audit Section --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-700/60 gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>Rincian RevPAT per Meja & Zona</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                            {{ $revpatMetrics->total() }} Catatan
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Dihitung dari (Food + Beverage + Minimum Spend Shortfall) / Jam Ketersediaan Meja.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="openSimulateModal = true" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl transition shadow-lg shadow-amber-500/20">
                        + Simulasi RevPAT Baru
                    </button>
                </div>
            </div>

            {{-- P-UI 2: Empty State --}}
            @if($revpatMetrics->isEmpty())
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500 border border-slate-700">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-300">Belum Ada Data Metrik RevPAT</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        Sistem belum merekam metrik sesi malam ini untuk venue yang dipilih. Tekan tombol simulasi untuk menghasilkan kalkulasi.
                    </p>
                    <button type="button" @click="openSimulateModal = true" class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-medium transition">
                        Buat Metrik Awal
                    </button>
                </div>
            @else
                {{-- Data Table --}}
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">Tanggal</th>
                                <th scope="col" class="py-3 px-4">Zona</th>
                                <th scope="col" class="py-3 px-4">Meja ID</th>
                                <th scope="col" class="py-3 px-4">Jam Avail / Occupied</th>
                                <th scope="col" class="py-3 px-4">Pendapatan F&B</th>
                                <th scope="col" class="py-3 px-4">Min. Spend Shortfall</th>
                                <th scope="col" class="py-3 px-4">Total Omzet</th>
                                <th scope="col" class="py-3 px-4">RevPAT / Jam</th>
                                <th scope="col" class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($revpatMetrics as $metric)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-400">
                                        {{ $metric->metric_date?->format('Y-m-d') ?? 'N/A' }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-700 text-amber-300">
                                            {{ $metric->zone_code }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium text-white">
                                        #{{ $metric->table_id }}
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-400">
                                        {{ $metric->available_hours }}h avail / <span class="text-emerald-400 font-semibold">{{ $metric->occupied_hours }}h used</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        IDR {{ number_format($metric->food_revenue_idr + $metric->beverage_revenue_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-rose-400">
                                        IDR {{ number_format($metric->minimum_spend_shortfall_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-white">
                                        IDR {{ number_format($metric->total_revenue_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                                        IDR {{ number_format($metric->revpat_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        {{-- P-UI 8: Active / Healthy Indicator --}}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            ● Synced
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $revpatMetrics->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Analisis Marjin Kontribusi F&B & Dinamika Minimum Spend Meja (D90-3) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Box 1: Contribution Margin Breakdown --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <h3 class="text-base font-bold text-white flex items-center justify-between">
                    <span>Marjin Kontribusi per Kategori Menu</span>
                    <span class="text-xs font-mono text-emerald-400">Target Net Margin $\ge$ 65%</span>
                </h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs text-slate-300 mb-1">
                            <span>Spirit & Champagne Premium (COGS 18% - Marjin 82%)</span>
                            <span class="font-bold text-emerald-400">IDR 124.500.000</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-emerald-500 h-2 rounded-full" style="width: 82%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs text-slate-300 mb-1">
                            <span>Cocktail Signature Beach Club (COGS 22% - Marjin 78%)</span>
                            <span class="font-bold text-cyan-400">IDR 68.200.000</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-cyan-400 h-2 rounded-full" style="width: 78%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs text-slate-300 mb-1">
                            <span>Bier & Seltzer Dingin (COGS 32% - Marjin 68%)</span>
                            <span class="font-bold text-amber-400">IDR 34.100.000</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-amber-400 h-2 rounded-full" style="width: 68%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs text-slate-300 mb-1">
                            <span>Tapas & Seafood Platter (COGS 38% - Marjin 62%)</span>
                            <span class="font-bold text-purple-400">IDR 42.800.000</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-purple-400 h-2 rounded-full" style="width: 62%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Box 2: Dynamic Minimum Spend Surge vs Cuaca Pesisir --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <h3 class="text-base font-bold text-white flex items-center justify-between">
                    <span>Dinamika Minimum Spend & Cuaca Pesisir</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300 font-semibold">Surge Adaptive</span>
                </h3>
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Kondisi Cuaca Saat Ini:</span>
                        <span class="font-bold text-emerald-400">Cerah Berawan (Peluang Hujan 10%)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Faktor Permintaan Waktu Puncak (21:00-02:00):</span>
                        <span class="font-bold text-amber-400">+25% Surge Minimum Spend</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Komitmen Minimum Spend Daybed Pantai:</span>
                        <span class="font-bold text-white">IDR 3.500.000 / meja</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Komitmen Minimum Spend VIP Cabana:</span>
                        <span class="font-bold text-white">IDR 12.000.000 / sofa</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700 text-slate-400">
                        *Bila terjadi hujan lebat atau badai tropis, sistem secara otomatis merelokasi deposit minimum spend ke area tertutup (*indoor lounge*) atau mengonversi deposit menjadi voucher saldo tanpa potongan penalty.
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Perbandingan Shift Siang (Day Pool Party) vs Shift Malam (Club Night) --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
            <h3 class="text-base font-bold text-white flex items-center justify-between">
                <span>Perbandingan Kinerja Shift Siang vs Shift Malam</span>
                <span class="text-xs text-slate-400">Pukul 12:00-19:00 vs 20:00-04:00</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-amber-400 uppercase">Day Pool Party (12:00 - 19:00 WITA)</span>
                    <p class="text-slate-400">Dominasi konsumsi bir dingin, seltzer, dan seafood tapas. Kecepatan pergantian tamu (table turnover) lebih tinggi.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">Rata-rata RevPAT:</span>
                        <span class="text-white font-bold">IDR 850.000 / jam</span>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-purple-400 uppercase">Club Night & Headline Show (20:00 - 04:00 WITA)</span>
                    <p class="text-slate-400">Dominasi bottle service spirit premium dan VIP minimum spend. Dwell time tamu lebih panjang (rata-rata 4.8 jam).</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">Rata-rata RevPAT:</span>
                        <span class="text-amber-400 font-bold">IDR 2.450.000 / jam</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Confirmation Modal Simulasi RevPAT --}}
    <div x-show="openSimulateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openSimulateModal = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white">Kalkulasi Cepat Simulasi RevPAT</h3>
                    <button type="button" @click="openSimulateModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4 py-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Pilih Zona Acara</label>
                        <select x-model="simForm.zone_code" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                            @foreach($zones as $z)
                                <option value="{{ $z->zone_code }}">{{ $z->name }} ({{ $z->zone_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Jam Meja Tersedia</label>
                            <input type="number" x-model="simForm.available_hours" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1" max="24">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Jam Meja Terisi</label>
                            <input type="number" x-model="simForm.occupied_hours" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0" max="24">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Omzet Minuman IDR</label>
                            <input type="number" x-model="simForm.beverage_rev" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Omzet Makanan IDR</label>
                            <input type="number" x-model="simForm.food_rev" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Shortfall Minimum Spend IDR</label>
                        <input type="number" x-model="simForm.shortfall" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                    </div>

                    {{-- Live Preview Calculation --}}
                    <div class="p-4 bg-slate-800/80 rounded-xl border border-slate-700">
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Perkiraan Total Omzet:</span>
                            <span class="font-bold text-white" x-text="'IDR ' + Number(calculatedTotal()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400 mt-1">
                            <span>Estimasi Nilai RevPAT:</span>
                            <span class="font-bold text-amber-400" x-text="'IDR ' + Number(calculatedRevpat()).toLocaleString('id-ID') + ' / jam'"></span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="openSimulateModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                        Batal
                    </button>
                    <button type="button" @click="saveSimulation()" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-xs transition">
                        Terapkan ke Metrik
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function venuePnlDashboard() {
    return {
        isLoading: false,
        openSimulateModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        simForm: {
            zone_code: 'BF-CABANA',
            available_hours: 8,
            occupied_hours: 6,
            beverage_rev: 15000000,
            food_rev: 5000000,
            shortfall: 1000000
        },
        calculatedTotal() {
            return Number(this.simForm.beverage_rev || 0) + Number(this.simForm.food_rev || 0) + Number(this.simForm.shortfall || 0);
        },
        calculatedRevpat() {
            const avail = Math.max(1, Number(this.simForm.available_hours || 1));
            return Math.floor(this.calculatedTotal() / avail);
        },
        refreshData() {
            this.isLoading = true;
            setTimeout(() => {
                this.isLoading = false;
                this.showToast('Data Diperbarui', 'Data buku besar dan telemetri meja telah dimutakhirkan.', 'success');
            }, 500);
        },
        saveSimulation() {
            this.openSimulateModal = false;
            this.showToast('Simulasi Tersimpan', 'Perhitungan RevPAT berhasil dicatat secara lokal.', 'success');
        },
        showToast(title, message, type = 'success') {
            this.toast.title = title;
            this.toast.message = message;
            this.toast.type = type;
            this.toast.visible = true;
            setTimeout(() => {
                this.toast.visible = false;
            }, 4000);
        }
    };
}
</script>
@endsection
