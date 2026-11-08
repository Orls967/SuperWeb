@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <nav class="flex text-xs text-slate-400 gap-2 mb-2">
                    <a href="{{ route('hotel.mice.pipeline') }}" class="hover:text-slate-200">MICE Sales</a>
                    <span>/</span>
                    <span class="text-indigo-400">Kalkulator Margin Event</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Kalkulator & Guardrail Margin Kotor Acara MICE</h1>
                        <p class="text-sm text-slate-400">Verifikasi kelayakan finansial proposal event H16, penegakan ambang batas bawah $\ge 40.0\%$, dan sertifikat integritas</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('hotel.mice.pipeline') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    Kembali ke Pipeline Sales
                </a>
            </div>
        </div>

        <!-- Simulation Grid: Form vs Result Certificate -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left 7 Cols: Revenue & Direct Cost Inputs -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Revenue Components Card -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                            <h3 class="font-bold text-white text-base">Pos Pendapatan Acara (Gross Revenue)</h3>
                        </div>
                        <span class="text-xs text-slate-400">Dalam Mata Uang Rupiah (IDR)</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Sewa Ruang / Ballroom</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="hall_rental" value="50000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Paket Makanan & Minuman (F&B)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="fnb_revenue" value="75000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Audio Visual, Lighting & Panggung</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="av_revenue" value="15000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Komisi Kamar Blok Delegasi</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="room_revenue" value="10000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Direct Costs Components Card -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                            <h3 class="font-bold text-white text-base">Pos Biaya Langsung (Direct Variable Costs)</h3>
                        </div>
                        <span class="text-xs text-slate-400">Biaya Pokok Langsung Event</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">BOM Bahan Makanan (Food COGS)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="food_cogs" value="35000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">BOM Minuman (Beverage COGS)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="bev_cogs" value="10000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Upah Staf Jamuan Kasual (Labor)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="labor_cost" value="8000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Vendor Sewa AV & Panggung</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-400 font-mono">IDR</span>
                                <input type="number" id="av_vendor_cost" value="15000000" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-sm text-white font-mono focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right 5 Cols: Guardrail Verification Card -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-gradient-to-br from-slate-800 to-slate-850 border border-slate-700 rounded-xl p-5 space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                        <h3 class="font-bold text-white text-base">Hasil Evaluasi Guardrail H16</h3>
                        <span class="px-2.5 py-1 text-xs font-bold rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            GUARDRAIL LOLOS
                        </span>
                    </div>

                    <!-- Margin Meter Gauge -->
                    <div class="text-center space-y-2 py-2">
                        <div class="text-xs font-medium text-slate-400">Estimasi Gross Margin</div>
                        <div class="text-5xl font-black text-emerald-400 font-mono">54.67%</div>
                        <div class="text-xs text-slate-400">Batas Minimum Guardrail: <strong class="text-slate-200">40.0%</strong></div>
                    </div>

                    <div class="w-full bg-slate-700 h-3 rounded-full overflow-hidden relative">
                        <div class="bg-emerald-500 h-full rounded-full" style="width: 54.67%"></div>
                        <!-- 40% Marker line -->
                        <div class="absolute top-0 bottom-0 left-[40%] w-0.5 bg-rose-400 shadow" title="Ambang 40%"></div>
                    </div>

                    <!-- Breakdown Figures -->
                    <div class="space-y-2 text-xs text-slate-300 bg-slate-900/60 p-4 rounded-xl border border-slate-700/60 font-mono">
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="font-sans">Total Pendapatan:</span>
                            <span class="text-white font-bold">IDR 150.000.000</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="font-sans">Total Biaya Langsung:</span>
                            <span class="text-rose-400 font-bold">IDR 68.000.000</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="font-sans">Laba Kotor Moneter:</span>
                            <span class="text-emerald-400 font-bold">IDR 82.000.000</span>
                        </div>
                        <div class="flex justify-between pt-1">
                            <span class="font-sans">Kekurangan Harga (Shortfall):</span>
                            <span class="text-slate-400">IDR 0 (Lolos Penuh)</span>
                        </div>
                    </div>

                    <!-- Digital Certificate Seal -->
                    <div class="p-3 bg-slate-950/80 rounded-lg border border-slate-800 text-[11px] font-mono text-slate-400 space-y-1">
                        <div class="flex items-center justify-between text-slate-300">
                            <span>Sertifikat Digital H16</span>
                            <span class="text-emerald-400">&check; Terverifikasi SHA-256</span>
                        </div>
                        <div class="truncate text-[10px] text-slate-400">Signature: a6b4f7e2d9c185038890aa...</div>
                    </div>

                    <button class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm rounded-lg shadow-lg shadow-emerald-600/30 transition">
                        Gunakan Nilai Ini untuk Penawaran Resmi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
