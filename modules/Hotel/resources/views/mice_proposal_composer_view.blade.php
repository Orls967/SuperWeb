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
                    <span class="text-indigo-400">Proposal Composer</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-indigo-500/20 text-indigo-400 rounded-lg border border-indigo-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Composer Proposal Penawaran MICE Multi-Komponen</h1>
                        <p class="text-sm text-slate-400">Penyusunan paket ballroom, jamuan banquet, kamar blok, stan pameran dengan penguncian harga penawaran (*timelock*)</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    Simpan Draf Proposal
                </button>
                <button type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-indigo-600/30 transition flex items-center gap-2">
                    Terbitkan Penawaran Resmi & Kunci Timelock
                </button>
            </div>
        </div>

        <!-- Proposal Builder Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left 8 Cols: Form Sections -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Client & Lead Identity Card -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <h3 class="font-bold text-white text-base pb-2 border-b border-slate-700">1. Data Klien & Informasi Acara</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Nama Perusahaan / Organisasi</label>
                            <input type="text" value="Asosiasi Eksportir Indonesia (GPEI)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-medium focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Nama PIC Penanggung Jawab</label>
                            <input type="text" value="Bambang Soedirman (Sekretaris Jenderal)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-medium focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Judul Acara Formal</label>
                            <input type="text" value="Musyawarah Nasional & Expo Ekspor Indonesia 2026" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-medium focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Estimasi Jumlah Peserta (Pax)</label>
                            <input type="number" value="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Multi-Component Pricing Config -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <h3 class="font-bold text-white text-base pb-2 border-b border-slate-700">2. Rincian Komponen Paket & Penawaran Harga</h3>
                    
                    <div class="space-y-3 text-xs">
                        <!-- Component: Ballroom -->
                        <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-200">Sewa Grand Convention Hall (Full Day 8 Jam)</span>
                                <span class="text-emerald-400 font-mono font-bold">IDR 60.000.000</span>
                            </div>
                            <p class="text-slate-400 text-[11px]">Termasuk pemakaian panggung utama, karpet merah VIP, dan pendingin AC terpusat.</p>
                        </div>

                        <!-- Component: F&B Banquet -->
                        <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-200">Paket Jamuan Makan (2x Coffee Break + 1x Buffet Lunch 500 Pax)</span>
                                <span class="text-emerald-400 font-mono font-bold">IDR 175.000.000</span>
                            </div>
                            <p class="text-slate-400 text-[11px]">Menu Nusantara Grand Gala: 18 pilihan menu prasmanan + live cooking station.</p>
                        </div>

                        <!-- Component: Room Block Allotment -->
                        <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-200">Alokasi Kamar Blok Delegasi (150 Kamar Deluxe x 2 Malam)</span>
                                <span class="text-emerald-400 font-mono font-bold">IDR 300.000.000</span>
                            </div>
                            <p class="text-slate-400 text-[11px]">Tarif kontrak grup IDR 1.000.000/malam. Pelepasan bertingkat H-30 (25%), H-14 (50%), H-7 (100%).</p>
                        </div>

                        <!-- Component: Exhibition Booths -->
                        <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-200">Sewa Stan Expo Pameran Dagang (20 Stan Shell Scheme 3x3m)</span>
                                <span class="text-emerald-400 font-mono font-bold">IDR 80.000.000</span>
                            </div>
                            <p class="text-slate-400 text-[11px]">Lengkap dengan daya listrik 1 kVA per stan dan koneksi Wi-Fi ekshibisi.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right 4 Cols: Live Margin & Timelock Status -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Live Margin Feedback -->
                <div class="bg-gradient-to-br from-slate-800 to-slate-850 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-700">
                        <h4 class="text-sm font-bold text-white uppercase tracking-wider">Kalkulasi Margin Real-Time</h4>
                        <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            GUARDRAIL LOLOS
                        </span>
                    </div>

                    <div class="space-y-2 text-xs font-mono">
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="text-slate-400 font-sans">Total Harga Jual:</span>
                            <span class="text-white font-bold">IDR 615.000.000</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="text-slate-400 font-sans">Estimasi Biaya Pokok:</span>
                            <span class="text-rose-400 font-bold">IDR 295.000.000</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span class="text-slate-400 font-sans">Laba Kotor Proyeksi:</span>
                            <span class="text-emerald-400 font-bold">IDR 320.000.000</span>
                        </div>
                        <div class="flex justify-between pt-1 text-sm font-bold">
                            <span class="text-slate-200 font-sans">Gross Margin %:</span>
                            <span class="text-emerald-400 font-mono">52.03%</span>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-900/80 rounded-lg border border-slate-700/80 space-y-1.5 text-xs font-sans">
                        <div class="font-bold text-amber-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Masa Berlaku Penawaran (Timelock)
                        </div>
                        <p class="text-slate-400 text-[11px]">Penawaran harga ini akan dikunci selama 14 hari sejak diterbitkan. Perubahan harga bahan baku setelahnya tidak mempengaruhi kontrak.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

