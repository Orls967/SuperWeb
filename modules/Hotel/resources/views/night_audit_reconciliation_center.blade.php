@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pusat Rekonsiliasi & Audit Malam (Night Audit)</h1>
            <p class="text-sm text-slate-500">Verifikasi dua sumber (Folio vs Ledger), penutupan tanggal bisnis, dan deteksi diskrepansi.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Ekspor Jurnal Audit Harian
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                Jalankan Siklus Audit Malam
            </button>
        </div>
    </div>

    <!-- Pengecekan Keseimbangan Dua Sumber -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-4">Hasil Validasi Keseimbangan Buku Besar (Dual Source Verification)</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-lg bg-slate-50 p-4 border border-slate-200">
                <span class="text-xs font-mono text-slate-400 uppercase">Sumber 1: Agregasi Baris Folio Fisik</span>
                <div class="mt-2 text-xl font-bold text-slate-800">Rp 48.650.000</div>
                <p class="text-xs text-slate-500 mt-1">Total pendapatan sewa kamar malam berjalan</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4 border border-slate-200">
                <span class="text-xs font-mono text-slate-400 uppercase">Sumber 2: Snapshot Night Audit Ledger</span>
                <div class="mt-2 text-xl font-bold text-slate-800">Rp 48.650.000</div>
                <p class="text-xs text-slate-500 mt-1">Akun: htl:room_revenue:IDR</p>
            </div>
            <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-center flex flex-col justify-center">
                <span class="text-xs font-bold text-emerald-800 uppercase">Status Diskrepansi</span>
                <div class="mt-1 text-2xl font-black text-emerald-600">Rp 0 (BALANCE)</div>
                <p class="text-xs text-emerald-700 mt-1">Audit dua arah lolos bersih 100%</p>
            </div>
        </div>
    </div>

    <!-- Riwayat Penutupan Tanggal Bisnis -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Log Penutupan Tanggal Bisnis Properti (Audit Trail)
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Tanggal Bisnis</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Kamar Terjual</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Okupansi (%)</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">ADR (Rp)</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">RevPAR (Rp)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Snapshot</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">2026-11-07</td>
                    <td class="px-6 py-4 text-center font-mono">84 / 100</td>
                    <td class="px-6 py-4 text-center font-bold text-slate-800">84.0%</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-800">1.150.000</td>
                    <td class="px-6 py-4 text-right font-mono text-emerald-600">966.000</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">CLOSED & AUDITED</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">2026-11-06</td>
                    <td class="px-6 py-4 text-center font-mono">78 / 100</td>
                    <td class="px-6 py-4 text-center font-bold text-slate-800">78.0%</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-800">1.120.000</td>
                    <td class="px-6 py-4 text-right font-mono text-emerald-600">873.600</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">CLOSED & AUDITED</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
