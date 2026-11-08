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
                    <span class="text-indigo-400">Rekonsiliasi Master Folio</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Rekonsiliasi Master Folio MICE & Audit Ledger</h1>
                        <p class="text-sm text-slate-400">Pencocokan mutasi pembukuan berpasangan (Double-Entry), split tagihan City Ledger AR, dan verifikasi zero discrepancy</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    Ekspor Berita Acara Rekonsiliasi
                </button>
                <button type="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-emerald-600/30 transition flex items-center gap-2">
                    Tutup Master Folio & Terbitkan Final AR
                </button>
            </div>
        </div>

        <!-- Master Balance Status Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs text-slate-400 uppercase font-semibold">Total Tagihan Master Kontrak</div>
                <div class="text-2xl font-bold text-white font-mono mt-1">IDR 380.000.000</div>
                <div class="text-xs text-slate-400 mt-1">CTR-MICE-2026-8812</div>
            </div>

            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs text-slate-400 uppercase font-semibold">Pembayaran Termin Diterima</div>
                <div class="text-2xl font-bold text-emerald-400 font-mono mt-1">IDR 380.000.000</div>
                <div class="text-xs text-emerald-400 mt-1">&check; 4 Termin Lunas Sempurna</div>
            </div>

            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs text-slate-400 uppercase font-semibold">Sisa Tagihan Tertunggak</div>
                <div class="text-2xl font-bold text-slate-300 font-mono mt-1">IDR 0</div>
                <div class="text-xs text-emerald-400 mt-1">Zero Balance Cleared</div>
            </div>

            <div class="bg-slate-800/60 border border-emerald-500/40 rounded-xl p-4">
                <div class="text-xs text-slate-400 uppercase font-semibold">Status Rekonsiliasi Ledger</div>
                <div class="text-lg font-bold text-emerald-400 mt-1">RECONCILED</div>
                <div class="text-xs text-slate-400 mt-1 font-mono">Key: HTL-MICE-REC-8812...</div>
            </div>
        </div>

        <!-- Ledger Entries Breakdown Table -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-700">
                <h3 class="font-bold text-white text-base">Jurnal Pembukuan Berpasangan (Double-Entry General Ledger)</h3>
                <span class="text-xs text-slate-400 font-mono">Aturan Idempotensi A4 Terpenuhi</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-800 text-xs uppercase font-semibold text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Tanggal Mutasi</th>
                            <th class="p-3">Akun Akuntansi</th>
                            <th class="p-3">Deskripsi Transaksi</th>
                            <th class="p-3 text-right">Debit (IDR)</th>
                            <th class="p-3 text-right">Kredit (IDR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 font-mono text-xs">
                        <tr>
                            <td class="p-3">2026-11-08</td>
                            <td class="p-3 text-indigo-400 font-bold">1-1100 Kas & Bank Mandiri</td>
                            <td class="p-3 font-sans">Penerimaan Deposit Termin 1 (20% Kontrak MICE)</td>
                            <td class="p-3 text-right text-emerald-400">76.000.000</td>
                            <td class="p-3 text-right text-slate-500">0</td>
                        </tr>
                        <tr>
                            <td class="p-3">2026-11-08</td>
                            <td class="p-3 text-indigo-400 font-bold">2-1300 Pendapatan Diterima Dimuka</td>
                            <td class="p-3 font-sans">Kredit Uang Muka Acara MICE (Unearned Revenue)</td>
                            <td class="p-3 text-right text-slate-500">0</td>
                            <td class="p-3 text-right text-emerald-400">76.000.000</td>
                        </tr>
                        <tr>
                            <td class="p-3">2026-11-24</td>
                            <td class="p-3 text-indigo-400 font-bold">2-1300 Pendapatan Diterima Dimuka</td>
                            <td class="p-3 font-sans">Pengakuan Pendapatan Setelah Acara Selesai Terlaksana</td>
                            <td class="p-3 text-right text-emerald-400">380.000.000</td>
                            <td class="p-3 text-right text-slate-500">0</td>
                        </tr>
                        <tr>
                            <td class="p-3">2026-11-24</td>
                            <td class="p-3 text-indigo-400 font-bold">4-1100 Pendapatan Sewa Ballroom & F&B</td>
                            <td class="p-3 font-sans">Pengakuan Pendapatan Usaha Acara MICE Final</td>
                            <td class="p-3 text-right text-slate-500">0</td>
                            <td class="p-3 text-right text-emerald-400">380.000.000</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-slate-700 bg-slate-800/80 font-bold text-xs">
                        <tr>
                            <td colspan="3" class="p-3 text-right font-sans text-slate-300">Total Keseimbangan Jurnal Ledger:</td>
                            <td class="p-3 text-right text-emerald-400 font-mono">IDR 456.000.000</td>
                            <td class="p-3 text-right text-emerald-400 font-mono">IDR 456.000.000</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
