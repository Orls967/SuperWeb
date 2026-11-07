@extends('layouts.app')

@section('title', 'Pasar Sekunder Pertukaran Hak Guna Vila Timeshare — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-amber-50 text-amber-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pasar Sekunder Pertukaran Hak Guna Vila Timeshare (P2P Swap)</h1>
                    <p class="text-sm text-slate-500">Pertukaran Minggu Kalender Menginap Antar-Pemilik Token Vila Fraksional, Kalkulasi Balancing Fee Musiman & Sertifikasi Hak H8</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    + Ajukan Permohonan Tukar Minggu (Swap)
                </button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Unit Vila Fraksional Aktif</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">12 Vila Mewah</p>
                <span class="text-xs text-slate-400 mt-1 block">1.200 Total Lembar Token Fraksi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Permohonan Swap Terbuka</span>
                <p class="text-2xl font-bold text-amber-600 mt-1">14 Listing</p>
                <span class="text-xs text-amber-500 mt-1 block">Menunggu Konfirmasi Mitra Tukar</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Transaksi Swap Sukses Tahun Ini</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">42 Pertukaran</p>
                <span class="text-xs text-emerald-500 mt-1 block">100% Sertifikat H8 Tervalidasi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Balancing Fee Terkumpul</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">IDR 21.000.000</p>
                <span class="text-xs text-indigo-500 mt-1 block">Akun htl:revenue:swap_fee:IDR</span>
            </div>
        </div>

        <!-- Available Swap Listings Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daftar Penawaran Pertukaran Minggu Menginap Terverifikasi</h2>
                    <p class="text-xs text-slate-500">Mekanisme pertukaran langsung terjamin aman tanpa risiko kepemilikan ganda</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">ID Listing Swap</th>
                            <th class="px-6 py-3">Unit Vila Asal</th>
                            <th class="px-6 py-3">Minggu Asal & Musim</th>
                            <th class="px-6 py-3">Minggu Target Dicari</th>
                            <th class="px-6 py-3 text-right">Biaya Penyeimbang (Fee)</th>
                            <th class="px-6 py-3 text-center">Status Bursa</th>
                            <th class="px-6 py-3 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-800">SWAP-2026-9201</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Royal Beachfront Pool Villa 01</span>
                                <span class="text-xs text-slate-400">Pemilik Token ID: INV-ALICE</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Minggu ke-18 (Mei)</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-blue-100 text-blue-800 rounded">REGULAR SEASON</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Minggu ke-28 (Juli)</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-rose-100 text-rose-800 rounded">PEAK SUMMER</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 500.000</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                    AVAILABLE
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                    Setujui Swap
                                </button>
                            </td>
                        </tr>

                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-800">SWAP-2026-9202</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Sunset Cliff Panorama Villa 04</span>
                                <span class="text-xs text-slate-400">Pemilik Token ID: INV-BOB</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Minggu ke-42 (Oktober)</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-800 rounded">SHOULDER SEASON</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Minggu ke-51 (Desember)</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-rose-100 text-rose-800 rounded">PEAK YEAR-END</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 750.000</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    UNDER REVIEW
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition">
                                    Detail Listing
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Timeshare Rental Yield Calculator Component -->
        @include('hotel::components.timeshare_rental_yield_calculator')
    </div>
</div>
@endsection
