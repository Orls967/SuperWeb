@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gerbang Akses Lounge Eksekutif VIP (Executive Lounge Turnstile)</h1>
            <p class="text-sm text-slate-500">Pemeriksaan hak masuk lounge lantai atas untuk tamu kamar tipe Suite dan pemegang keanggotaan Platinum.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                Pindai Kunci Digital Tamu Masuk
            </button>
        </div>
    </div>

    <!-- Metrik Kunjungan Lounge -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Tamu Sedang di Lounge</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">18 Orang</div>
            <p class="text-xs text-slate-500 mt-1">Sesi Afternoon Tea berlangsung</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kapasitas Tempat Duduk Lounge</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">45 Kursi</div>
            <p class="text-xs text-slate-500 mt-1">Tingkat keterisian 40% (Nyaman)</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Akses Ditolak (Non-Eligible)</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">2 Percobaan</div>
            <p class="text-xs text-slate-500 mt-1">Kamar Deluxe standar tanpa addon</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Konsumsi Canapé & Mocktail</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Optimal</div>
            <p class="text-xs text-slate-500 mt-1">Stok replenishment siap</p>
        </div>
    </div>

    <!-- Log Kunjungan Lounge Terkini -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Riwayat Akses Tamu ke Executive Lounge Hari Ini
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Waktu Akses</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama Tamu</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nomor Kamar</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kriteria Kelayakan</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">15:32:10</td>
                    <td class="px-6 py-4 font-semibold text-slate-900">Michael Armstrong</td>
                    <td class="px-6 py-4 text-slate-700">Kamar 502</td>
                    <td class="px-6 py-4 text-slate-600">Executive Penthouse Suite Guest</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold">GRANTED</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">15:10:44</td>
                    <td class="px-6 py-4 font-semibold text-slate-900">Dewi Anggraini</td>
                    <td class="px-6 py-4 text-slate-700">Kamar 308</td>
                    <td class="px-6 py-4 text-slate-600">Global Travel Pass Platinum Member</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold">GRANTED</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
