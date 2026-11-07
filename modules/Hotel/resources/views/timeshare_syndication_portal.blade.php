@extends('layouts.app')

@section('title', 'Portal Sindikasi Timeshare & Hak Fraksional Villa — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-amber-50 text-amber-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Portal Kepemilikan Fraksional Timeshare</h1>
                    <p class="text-sm text-slate-500">Tokenisasi Aset Riil Villa Resort (RWA), Bursa Tukar Minggu & Hasil Dividen Harian</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    Resort Management Fee: 15% Net Yield
                </span>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Villa Ter-Sindikasi</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ count($units) }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Seminyak, Canggu, Ubud, Sanur</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Fraksi Token Beredar</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $units->sum('total_token_shares') }}</p>
                <span class="text-xs text-indigo-400 mt-1 block">1 Fraksi = 1 Minggu Hak Inap</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tarif Sewa Harian Rata-rata</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">IDR {{ number_format((float) $units->avg('daily_rental_rate_idr'), 0, ',', '.') }}</p>
                <span class="text-xs text-emerald-500 mt-1 block">Pasar Sewa Publik Hotel CRS</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Dividen Dibagikan (YTD)</span>
                <p class="text-2xl font-bold text-amber-600 mt-1">IDR 425.000.000</p>
                <span class="text-xs text-amber-500 mt-1 block">Distribusi pro-rata ke wallet investor</span>
            </div>
        </div>

        <!-- Units Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($units as $u)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-xs font-mono font-bold">
                                {{ $u->unit_code }}
                            </span>
                            <span class="text-xs font-medium text-slate-400">
                                {{ $u->total_token_shares }} Fraksi (52 Minggu)
                            </span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $u->unit_name }}</h3>
                            <p class="text-xs text-slate-500 mt-1">Vila mewah dengan kolam renang pribadi, pemandangan laut dan layanan butler 24 jam.</p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Tarif Sewa Publik:</span>
                            <span class="font-bold font-mono text-slate-800">IDR {{ number_format((float) $u->daily_rental_rate_idr, 0, ',', '.') }} / malam</span>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-500">Investor: <strong class="text-slate-700 font-mono">{{ $u->investors->count() }} Orang</strong></span>
                        <div class="flex gap-2">
                            <button class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium transition">
                                Atur Jadwal (H8)
                            </button>
                            <button class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-medium transition">
                                Bagi Dividen
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                    Belum ada unit timeshare terdaftar.
                </div>
            @endforelse
        </div>

        <!-- Timeshare Invariant Info -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-6">
            <h3 class="text-base font-bold text-indigo-950">Aturan Pengalihan Minggu yang Tidak Dipakai (Unutilized Weeks)</h3>
            <p class="text-xs text-indigo-800 mt-1 leading-relaxed">
                Pemilik unit fraksional berhak menempati unit selama kuota minggu yang dimiliki. Apabila pemilik tidak mengajukan reservasi hak tinggal paling lambat $T-14$ hari sebelum tanggal menginap, unit secara otomatis dialihkan ke <span class="font-bold underline">Hotel Public Rental Pool</span>. Pendapatan sewa kamar harian yang diperoleh dipotong biaya manajemen 15% dan disetorkan ke dompet dividen investor secara adil dan terverifikasi.
            </p>
        </div>

        <!-- Timeshare Rental Yield Calculator Component -->
        @include('hotel::components.timeshare_rental_yield_calculator')
    </div>
</div>
@endsection
