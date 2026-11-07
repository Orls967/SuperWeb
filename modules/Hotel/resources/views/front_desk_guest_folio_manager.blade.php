@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Front Desk: Folio & Billing Manager</h1>
            <p class="text-sm text-slate-500">Pemeriksaan tagihan tamu, split billing personal/corporate, dan penyelesaian folio.</p>
        </div>
        <div class="flex space-x-3">
            <a href="/hotel/front-desk" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Kembali ke Front Desk
            </a>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Cetak Folio Sementara
            </button>
        </div>
    </div>

    <!-- Panel Filter Folio -->
    <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-700">Nomor Kamar</label>
            <input type="text" placeholder="Contoh: 101" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700">Nama Tamu</label>
            <input type="text" placeholder="Nama depan / belakang" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700">Status Folio</label>
            <select class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="OPEN">OPEN (Aktif In-House)</option>
                <option value="SETTLED">SETTLED (Lunas)</option>
                <option value="TRANSFERRED">TRANSFERRED (City Ledger)</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" class="w-full inline-flex justify-center items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                Cari Folio
            </button>
        </div>
    </div>

    <!-- Tabel Rincian Folio Terpilih -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div>
                <span class="text-xs font-mono text-slate-400">Folio ID: FOL-202611-0912</span>
                <div class="text-base font-bold text-slate-900">Reservasi #RSV-2026-91001 — Kamar 304 (Deluxe Ocean)</div>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400">Total Saldo Terbuka:</span>
                <div class="text-lg font-bold text-rose-600">Rp 2.475.000</div>
            </div>
        </div>

        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Bisnis</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Kategori Transaksi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Keterangan</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Debit (Rp)</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Kredit (Rp)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">2026-11-08</td>
                    <td class="px-6 py-4 font-semibold text-slate-800">ROOM_CHARGE</td>
                    <td class="px-6 py-4 text-slate-600">Sewa Kamar Harian + Service 5% + PBJT 10%</td>
                    <td class="px-6 py-4 text-right font-mono font-medium text-slate-900">1.380.000</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-400">-</td>
                    <td class="px-6 py-4 text-center">
                        <button class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Split ke Corporate</button>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">2026-11-08</td>
                    <td class="px-6 py-4 font-semibold text-slate-800">FNB_OUTLET</td>
                    <td class="px-6 py-4 text-slate-600">Makan Malam di Ocean Bistro Restaurant</td>
                    <td class="px-6 py-4 text-right font-mono font-medium text-slate-900">450.000</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-400">-</td>
                    <td class="px-6 py-4 text-center">
                        <button class="text-xs text-rose-600 hover:text-rose-900 font-medium">Koreksi / Reversal</button>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">2026-11-08</td>
                    <td class="px-6 py-4 font-semibold text-emerald-700">GUEST_DEPOSIT</td>
                    <td class="px-6 py-4 text-slate-600">Deposit Jaminan Awal Check-In (Kartu Kredit)</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-400">-</td>
                    <td class="px-6 py-4 text-right font-mono font-medium text-emerald-600">1.000.000</td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-xs text-slate-400">Locked Deposit</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
