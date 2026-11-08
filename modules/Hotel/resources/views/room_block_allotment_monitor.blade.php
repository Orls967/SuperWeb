@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <nav class="flex text-xs text-slate-400 gap-2 mb-2">
                    <a href="{{ route('hotel.mice.pipeline') }}" class="hover:text-slate-200">MICE Sales</a>
                    <span>/</span>
                    <span class="text-indigo-400">Allotment Kamar Blok</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-blue-500/20 text-blue-400 rounded-lg border border-blue-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Pemantau Alokasi Kamar Blok (Room Block Allotment)</h1>
                        <p class="text-sm text-slate-400">Pelepasan otomatis bertingkat H14 (H-30, H-14, H-7), perlindungan anti-oversell, dan integrasi G7-K16 RoomBlockReserver</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <form action="{{ route('hotel.mice.release-blocks.trigger') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-indigo-600/30 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Eksekusi Pelepasan Cutoff Sekarang
                    </button>
                </form>
            </div>
        </div>

        <!-- Cutoff Staged Explanation Box -->
        <div class="bg-slate-800/60 border border-slate-700/80 rounded-xl p-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Jadwal Pelepasan Bertingkat (Staged Cutoff Engine)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-3 bg-slate-900/50 rounded-lg border border-slate-700/50 flex items-start gap-3">
                    <span class="px-2 py-1 bg-amber-500/20 text-amber-400 rounded font-mono font-bold">H-30</span>
                    <div>
                        <div class="font-semibold text-slate-200">Cutoff Tahap 1 (Pelepasan 25%)</div>
                        <p class="text-slate-400 mt-0.5">25% dari sisa kamar yang belum di-pickup dilepaskan kembali ke ketersediaan publik CRS.</p>
                    </div>
                </div>

                <div class="p-3 bg-slate-900/50 rounded-lg border border-slate-700/50 flex items-start gap-3">
                    <span class="px-2 py-1 bg-orange-500/20 text-orange-400 rounded font-mono font-bold">H-14</span>
                    <div>
                        <div class="font-semibold text-slate-200">Cutoff Tahap 2 (Pelepasan 50%)</div>
                        <p class="text-slate-400 mt-0.5">50% dari sisa kamar yang belum diisi nama tamu dikembalikan ke inventori publik.</p>
                    </div>
                </div>

                <div class="p-3 bg-slate-900/50 rounded-lg border border-slate-700/50 flex items-start gap-3">
                    <span class="px-2 py-1 bg-rose-500/20 text-rose-400 rounded font-mono font-bold">H-7</span>
                    <div>
                        <div class="font-semibold text-slate-200">Final Cutoff (Pelepasan 100%)</div>
                        <p class="text-slate-400 mt-0.5">100% seluruh sisa kamar unpicked dilepas habis. Blok difinalisasi sesuai pickup aktual.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Block Cards Grid -->
        <div class="space-y-4">
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-700 pb-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 text-xs font-mono font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30 rounded">BLK-MICE-2026-001</span>
                            <span class="text-sm font-semibold text-white">Oceanology World Congress 2026</span>
                            <span class="px-2 py-0.5 text-xs bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded">Grand Hyatt Resort Bali</span>
                        </div>
                        <p class="text-xs text-slate-400">Periode Menginap: 20 - 24 November 2026 &bull; Tarif Kontrak: IDR 1.250.000 / Malam</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full text-xs font-semibold">
                            Cutoff H-14 Mendatang (Sisa 8 Hari)
                        </span>
                    </div>
                </div>

                <!-- Progress Bar & Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                    <div class="md:col-span-3 space-y-2">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Progres Pickup Delegasi: <strong class="text-white">110 / 165 Kamar (66.7%)</strong></span>
                            <span class="text-slate-400">Sisa Unpicked: <strong class="text-amber-400">55 Kamar</strong></span>
                        </div>
                        <div class="w-full bg-slate-700 h-3 rounded-full overflow-hidden flex">
                            <!-- Confirmed Pickup -->
                            <div class="bg-emerald-500 h-full" style="width: 66.7%" title="Confirmed Pickup: 110"></div>
                            <!-- Unpicked remaining -->
                            <div class="bg-amber-500/60 h-full" style="width: 33.3%" title="Unpicked: 55"></div>
                        </div>
                        <div class="flex items-center gap-4 text-[11px] text-slate-400">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> 110 Terkonfirmasi</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500/60"></span> 55 Menunggu Rooming List</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-slate-600"></span> 35 Dilepas pada H-30</span>
                        </div>
                    </div>

                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60 text-right space-y-1">
                        <div class="text-xs text-slate-400">Alokasi Awal Kontrak</div>
                        <div class="text-lg font-bold text-white font-mono">200 Kamar</div>
                        <div class="text-[11px] text-emerald-400">Invarian $K \ge P$ Terbukti</div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-700 pb-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 text-xs font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 rounded">BLK-WED-2026-088</span>
                            <span class="text-sm font-semibold text-white">Kevin & Natasha Wedding Family Block</span>
                            <span class="px-2 py-0.5 text-xs bg-pink-500/10 text-pink-400 border border-pink-500/20 rounded">Bridal Suite Upgraded</span>
                        </div>
                        <p class="text-xs text-slate-400">Periode Menginap: 10 - 12 Desember 2026 &bull; Tarif Spesial Keluarga: IDR 1.100.000 / Malam</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-xs font-semibold">
                            Status Proteksi Penuh (> 30 Hari)
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                    <div class="md:col-span-3 space-y-2">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Progres Pickup Keluarga: <strong class="text-white">18 / 25 Kamar (72.0%)</strong></span>
                            <span class="text-slate-400">Sisa Unpicked: <strong class="text-amber-400">7 Kamar</strong></span>
                        </div>
                        <div class="w-full bg-slate-700 h-3 rounded-full overflow-hidden flex">
                            <div class="bg-emerald-500 h-full" style="width: 72%" title="Confirmed: 18"></div>
                            <div class="bg-amber-500/60 h-full" style="width: 28%" title="Unpicked: 7"></div>
                        </div>
                        <div class="flex items-center gap-4 text-[11px] text-slate-400">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> 18 Kamar Terisi</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500/60"></span> 7 Kamar Perlindungan H-30</span>
                        </div>
                    </div>

                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60 text-right space-y-1">
                        <div class="text-xs text-slate-400">Total Kamar Blok</div>
                        <div class="text-lg font-bold text-white font-mono">25 Kamar</div>
                        <div class="text-[11px] text-purple-400">Termasuk 1 Presidential Suite</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MICE Room Block Slippage & Attrition Gauge -->
        @include('hotel::components.mice_attrition_risk_gauge')
    </div>
</div>
@endsection
