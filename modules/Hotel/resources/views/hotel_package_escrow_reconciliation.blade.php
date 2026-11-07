@extends('layouts.app')

@section('title', 'Menara Audit Rekonsiliasi Escrow Paket Multi-Vendor — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-purple-50 text-purple-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Menara Audit Rekonsiliasi Escrow Paket Multi-Vendor</h1>
                    <p class="text-sm text-slate-500">Kepatuhan PSAK 72 / IFRS 15 Standalone Selling Price (SSP), Verifikasi Milestone Pemenuhan Layanan & Kliring Likuiditas</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                    Discrepancy: IDR 0 (Balanced)
                </span>
                <button class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    Jalankan Audit Batch Escrow
                </button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Dana Titipan Escrow</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">IDR 175.400.000</p>
                <span class="text-xs text-slate-400 mt-1 block">Akun htl:escrow:destination_package:IDR</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Dana Berhasil Dicairkan</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">IDR 112.500.000</p>
                <span class="text-xs text-emerald-500 mt-1 block">64.1% Milestone Terpenuhi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Saldo Terkunci Menunggu Event</span>
                <p class="text-2xl font-bold text-purple-600 mt-1">IDR 62.900.000</p>
                <span class="text-xs text-purple-500 mt-1 block">35.9% Belum Mencapai Milestone</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Jumlah Klaim Sengketa (Dispute)</span>
                <p class="text-2xl font-bold text-slate-400 mt-1">0 Kasus</p>
                <span class="text-xs text-emerald-600 font-semibold mt-1 block">Tingkat Integritas Kliring 100%</span>
            </div>
        </div>

        <!-- Escrow Settlement Ledger Component -->
        @include('hotel::components.package_vendor_settlement_ledger')

        <!-- Vendor Distribution Matrix -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Distribusi Kliring Mitra Berdasarkan Rasio SSP Relatif</h3>
            <p class="text-xs text-slate-500">Pemecahan nilai transaksi paket liburan ke masing-masing kas vendor berdasarkan audit Standalone Selling Price</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800">Operator Hotel (Resort Stay)</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Porsi 60.0%</span>
                    </div>
                    <div class="text-lg font-bold text-slate-900 font-mono">IDR 105.240.000</div>
                    <div class="text-[11px] text-slate-400">Dicairkan saat tamu menyelesaikan proses check-in di front-desk.</div>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800">Operator Venue (Beach Club & Show)</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-purple-100 text-purple-800">Porsi 24.0%</span>
                    </div>
                    <div class="text-lg font-bold text-purple-700 font-mono">IDR 42.096.000</div>
                    <div class="text-[11px] text-slate-400">Dicairkan saat barcode tiket tamu berhasil dipindai di gerbang masuk.</div>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800">Armada Transportasi & Shuttle</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-sky-100 text-sky-800">Porsi 16.0%</span>
                    </div>
                    <div class="text-lg font-bold text-sky-700 font-mono">IDR 28.064.000</div>
                    <div class="text-[11px] text-slate-400">Dicairkan saat pengemudi shuttle menyelesaikan konfirmasi drop-off tamu.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
