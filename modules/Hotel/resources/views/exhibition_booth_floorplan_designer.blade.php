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
                    <span class="text-indigo-400">Pameran & Ekshibisi</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Denah Grid Stan Pameran & Utilitas Ekshibisi</h1>
                        <p class="text-sm text-slate-400">Konektor Fase 78, pemesanan stan modular 3x3m & 6x3m, alokasi daya kVA, LAN khusus, dan sensor arus pejalan kaki</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Audit Beban Listrik Panel
                </button>
                <button type="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-emerald-600/30 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Reservasi Stan Baru
                </button>
            </div>
        </div>

        <!-- Hall Floorplan Grid Viewer -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Floorplan Canvas -->
            <div class="lg:col-span-2 bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                    <div>
                        <h3 class="font-bold text-white text-base">Nusantara Convention Exhibition Hall (Hall A)</h3>
                        <p class="text-xs text-slate-400">Grid Resolusi 1 Meter &bull; Lorong Evakuasi 3 Meter Terlindungi</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-emerald-500 rounded-sm"></span> Terisi (6)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-slate-700 rounded-sm"></span> Tersedia (18)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-amber-500 rounded-sm"></span> Reserved (4)</span>
                    </div>
                </div>

                <!-- Interactive Grid Representation -->
                <div class="p-4 bg-slate-950/80 rounded-xl border border-slate-800 font-mono text-xs overflow-x-auto">
                    <div class="min-w-[500px] space-y-3">
                        <div class="text-center py-1 bg-slate-800/60 text-slate-400 rounded text-[11px] font-sans">
                            &uarr; Pintu Masuk Utama (Gerbang Gate Utara - IoT Sensor Terpasang) &uarr;
                        </div>

                        <!-- Row 1: Shell Scheme 3x3 -->
                        <div class="grid grid-cols-6 gap-3">
                            <div class="p-3 bg-emerald-950/40 border border-emerald-500/50 rounded-lg text-center space-y-1">
                                <div class="font-bold text-emerald-300">Stan A-01</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Shell</div>
                                <div class="text-[10px] text-emerald-400">PT Telkom Indonesia</div>
                                <div class="text-[9px] text-slate-400">2 kVA &bull; LAN ON</div>
                            </div>
                            <div class="p-3 bg-emerald-950/40 border border-emerald-500/50 rounded-lg text-center space-y-1">
                                <div class="font-bold text-emerald-300">Stan A-02</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Shell</div>
                                <div class="text-[10px] text-emerald-400">Pertamina Power</div>
                                <div class="text-[9px] text-slate-400">1 kVA</div>
                            </div>
                            <div class="p-3 bg-amber-950/40 border border-amber-500/50 rounded-lg text-center space-y-1">
                                <div class="font-bold text-amber-300">Stan A-03</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Shell</div>
                                <div class="text-[10px] text-amber-400">Holding Tentatif</div>
                                <div class="text-[9px] text-slate-400">0.5 kVA</div>
                            </div>
                            <div class="p-3 bg-slate-900 border border-slate-800 rounded-lg text-center space-y-1 hover:border-indigo-500 cursor-pointer transition">
                                <div class="font-bold text-slate-300">Stan A-04</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Kosong</div>
                                <div class="text-[10px] text-indigo-400">Pesan Stan &rarr;</div>
                            </div>
                            <div class="p-3 bg-slate-900 border border-slate-800 rounded-lg text-center space-y-1 hover:border-indigo-500 cursor-pointer transition">
                                <div class="font-bold text-slate-300">Stan A-05</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Kosong</div>
                                <div class="text-[10px] text-indigo-400">Pesan Stan &rarr;</div>
                            </div>
                            <div class="p-3 bg-slate-900 border border-slate-800 rounded-lg text-center space-y-1 hover:border-indigo-500 cursor-pointer transition">
                                <div class="font-bold text-slate-300">Stan A-06</div>
                                <div class="text-[10px] text-slate-400">3x3m &bull; Kosong</div>
                                <div class="text-[10px] text-indigo-400">Pesan Stan &rarr;</div>
                            </div>
                        </div>

                        <!-- Aisle 1 -->
                        <div class="py-2 text-center text-[10px] text-slate-400 tracking-widest bg-slate-900/40 rounded border border-dashed border-slate-800 font-sans">
                            ==== LORONG UTAMA PENJELAJAH (LEBAR 3 METER) ====
                        </div>

                        <!-- Row 2: Island Space Only 6x6 -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 bg-emerald-950/40 border border-emerald-500/60 rounded-xl text-center space-y-2">
                                <div class="flex items-center justify-between text-xs font-sans">
                                    <span class="font-bold text-emerald-300">Stan Island B-01 (6x6m Space Only)</span>
                                    <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-300 rounded text-[10px]">10 kVA 3-Phase</span>
                                </div>
                                <div class="text-sm font-semibold text-white">Bank Rakyat Indonesia (Main Sponsor Pavilion)</div>
                                <p class="text-[11px] text-slate-400 font-sans">Desain Khusus Booth Bertingkat &bull; Sensor IoT Footfall Terhubung</p>
                            </div>

                            <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl text-center space-y-2 hover:border-indigo-500 cursor-pointer transition">
                                <div class="flex items-center justify-between text-xs font-sans">
                                    <span class="font-bold text-slate-300">Stan Island B-02 (6x6m Space Only)</span>
                                    <span class="text-slate-400 text-[10px]">Tersedia</span>
                                </div>
                                <div class="text-sm text-slate-400 font-sans">Area Stan Pulau Terbuka 4 Sisi</div>
                                <div class="text-xs text-indigo-400 font-sans">IDR 45.000.000 + Utilitas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Col: Utilities & Footfall Telemetry -->
            <div class="space-y-6">
                <!-- Electrical & Network Utility Monitor -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white text-base">Beban Utilitas Hall A</h3>
                        <span class="text-xs text-emerald-400 font-mono">Sub-panel Safe</span>
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Beban Listrik Hall A:</span>
                                <strong class="text-white font-mono">18.5 / 45.0 kVA (41.1%)</strong>
                            </div>
                            <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full" style="width: 41.1%"></div>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Dedicated LAN Port Terpakai:</span>
                                <strong class="text-white font-mono">8 / 24 Port (33.3%)</strong>
                            </div>
                            <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-500 h-full rounded-full" style="width: 33.3%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footfall Sensor Live Analytics -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white text-base">Sensor Footfall Hall A</h3>
                        <span class="flex items-center gap-1.5 text-xs text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live MQTT
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/60">
                            <div class="text-xs text-slate-400">Arus Masuk (Hari Ini)</div>
                            <div class="text-lg font-bold text-white font-mono">1.420 Org</div>
                        </div>
                        <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700/60">
                            <div class="text-xs text-slate-400">Rata-rata Dwell Time</div>
                            <div class="text-lg font-bold text-amber-400 font-mono">24.5 Mnt</div>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400">Laporan metrik ROI stan pameran otomatis di-generate untuk para sponsor dan exhibitor terdaftar.</p>
                </div>
            </div>
        </div>

        <!-- Exhibition Loading Dock & Ingress Logistics Schedule -->
        @include('hotel::components.exhibition_loading_dock_schedule')
    </div>
</div>
@endsection
