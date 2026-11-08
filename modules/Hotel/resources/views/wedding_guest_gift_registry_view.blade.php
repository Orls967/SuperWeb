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
                    <a href="{{ route('hotel.mice.weddings') }}" class="hover:text-slate-200">Wedding Hub</a>
                    <span>/</span>
                    <span class="text-indigo-400">Gift Registry & Vendors</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-pink-500/20 text-pink-400 rounded-lg border border-pink-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Gift Registry Pernikahan & Koordinasi Vendor</h1>
                        <p class="text-sm text-slate-400">Pengelolaan kado tamu digital, sertifikasi higienitas vendor katering luar, dan jadwal penataan dekorasi pelaminan</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-pink-600/30 transition flex items-center gap-2">
                    Tambah Vendor Baru
                </button>
            </div>
        </div>

        <!-- Vendor & Registry Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Registered Vendors Table -->
            <div class="lg:col-span-2 bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                    <h3 class="font-bold text-white text-base">Daftar Vendor Eksternal Pernikahan Terverifikasi</h3>
                    <span class="text-xs text-slate-400">Pemeriksaan Surat Izin & SOP Beban Daya</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-4 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-white text-sm">Stupa Casavabanca Decoration</div>
                            <span class="px-2 py-0.5 text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-semibold">DEKORASI & FLORIST</span>
                        </div>
                        <p class="text-slate-400">Jadwal Load-in: H-1 Jam 22:00 WIB &bull; Alokasi Panggung Pelaminan 18 x 6 Meter</p>
                        <div class="flex items-center justify-between text-slate-400 pt-2 border-t border-slate-800">
                            <span>Beban Listrik Lampu: 4.5 kVA</span>
                            <span class="text-emerald-400">&check; Deposit Kerusakan IDR 10.000.000 Diterima</span>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-900/60 rounded-xl border border-slate-700 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-white text-sm">LeNovelle Cake Artistry</div>
                            <span class="px-2 py-0.5 text-xs bg-purple-500/10 text-purple-400 border border-purple-500/20 rounded font-semibold">WEDDING CAKE</span>
                        </div>
                        <p class="text-slate-400">Kue Pernikahan Arsitektur 7 Tingkat &bull; Pengiriman: Hari H Jam 13:00</p>
                        <div class="flex items-center justify-between text-slate-400 pt-2 border-t border-slate-800">
                            <span>Suhu Pendingin Chiller: 18°C Terjaga</span>
                            <span class="text-emerald-400">&check; Uji Sampel Higienitas Sanitasi Lulus</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Col: Digital Gift Registry -->
            <div class="space-y-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                        <h3 class="font-bold text-white text-base">Digital Gift Registry</h3>
                        <span class="text-xs text-pink-400 font-mono">Amplop QRIS</span>
                    </div>

                    <div class="p-4 bg-slate-900/60 rounded-xl border border-slate-700 text-center space-y-2">
                        <div class="text-xs text-slate-400">Total Amplop Digital Terkumpul</div>
                        <div class="text-3xl font-bold text-pink-400 font-mono">IDR 145.800.000</div>
                        <p class="text-[11px] text-slate-400">182 Transaksi QRIS Dinamis Terkonfirmasi Langsung ke Rekening Escrow</p>
                    </div>

                    <div class="space-y-2 text-xs text-slate-300">
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span>QRIS Dynamic Meja Registrasi:</span>
                            <span class="text-white font-mono font-bold">Aktif &bull; Terminal 01-04</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800">
                            <span>Kotak Kado Fisik Terkunci:</span>
                            <span class="text-emerald-400 font-medium">Diawasi Tim Security &bull; 2 CCTV</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

