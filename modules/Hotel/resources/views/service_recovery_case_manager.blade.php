@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manajemen Kasus & Pemulihan Layanan (Service Recovery)</h1>
            <p class="text-sm text-slate-500">Penyelesaian keluhan tamu, kalkulasi kompensasi bersertifikat H5, dan penerbitan voucher diskon/free night.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Ekspor Laporan Pemulihan Layanan
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Buat Kasus Kompensasi Baru
            </button>
        </div>
    </div>

    <!-- Ringkasan Kasus Service Recovery -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kasus Diselesaikan (Bulan Ini)</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">12 Kasus</div>
            <p class="text-xs text-slate-500 mt-1">Rata-rata resolusi < 2 jam</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Total Kompensasi Diterbitkan</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">Rp 6.850.000</div>
            <p class="text-xs text-slate-500 mt-1">Beban akun: htl:service_recovery_contra_revenue</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Voucher Free Night Diberikan</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">3 Sertifikat</div>
            <p class="text-xs text-slate-500 mt-1">Gangguan fasilitas berat (> 6 jam)</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Indeks Kepuasan Retensi Tamu</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">92.0%</div>
            <p class="text-xs text-slate-500 mt-1">Tamu kembali menginap (post-recovery)</p>
        </div>
    </div>

    <!-- Tabel Kasus Keluhan Tamu & Kompensasi -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Daftar Kasus Aktif & Riwayat Penyelesaian Keluhan Tamu
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">No. Kasus</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kamar & Tamu</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kategori Gangguan</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Durasi Gangguan</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Kompensasi (Rp)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Free Night?</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Resolusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">SRC-2026-091</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">Kamar 401 — Brian Davis</div>
                        <span class="text-xs text-slate-500">Res #RSV-2026-91022</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">AC Rusak & Kebocoran Pipa</td>
                    <td class="px-6 py-4 text-center font-mono text-slate-800">8.5 Jam</td>
                    <td class="px-6 py-4 text-right font-mono font-bold text-rose-600">750.000 (50%)</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">YA (GRANTED)</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold uppercase">RESOLVED</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">SRC-2026-092</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">Kamar 208 — Jessica Miller</div>
                        <span class="text-xs text-slate-500">Res #RSV-2026-91039</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">Keterlambatan Housekeeping (Kamar Belum Siap)</td>
                    <td class="px-6 py-4 text-center font-mono text-slate-800">2.0 Jam</td>
                    <td class="px-6 py-4 text-right font-mono font-bold text-slate-800">300.000 (25%)</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-semibold">TIDAK</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold uppercase">RESOLVED</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
