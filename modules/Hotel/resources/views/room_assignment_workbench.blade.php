@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Papan Alokasi Kamar & Optimasi Preferensi Tamu</h1>
            <p class="text-sm text-slate-500">Pencocokan kamar pintar berdasarkan riwayat menginap, preferensi lantai tinggi, dan kesiapan tata graha.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Segarkan Ketersediaan Kamar
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Alokasi Otomatis Batch Tamu Hari Ini
            </button>
        </div>
    </div>

    <!-- Ringkasan Kamar Siap Alokasi -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kamar Siap Huni (Inspected)</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">42 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Lolos verifikasi floor supervisor</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Tamu Belum Memiliki Kamar</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">18 Reservasi</div>
            <p class="text-xs text-slate-500 mt-1">Kedatangan terkonfirmasi hari ini</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Permintaan Tamu Khusus (VIP)</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">6 Tamu</div>
            <p class="text-xs text-slate-500 mt-1">Lantai tinggi & tenang (quiet wing)</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Skor Kepuasan Alokasi</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">96.8%</div>
            <p class="text-xs text-slate-500 mt-1">Algoritma alokasi preferensi</p>
        </div>
    </div>

    <!-- Antrean Reservasi Menunggu Penugasan Kamar -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Daftar Reservasi Menunggu Penugasan Kamar Fisik (Arrivals)
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">No. Reservasi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama Tamu & Tipe Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kategori Kamar</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Preferensi Tamu</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Rekomendasi Kamar</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">RSV-2026-91045</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">Alexander Wright</div>
                        <span class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Platinum Tier</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">Deluxe Ocean Suite</td>
                    <td class="px-6 py-4 text-slate-600">High Floor, Non-Smoking, King Bed</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-sky-100 text-sky-800 px-2 py-1 font-mono font-bold text-xs">Kamar 502 (Skor 95)</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" class="inline-flex items-center rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                            Konfirmasi Alokasi
                        </button>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">RSV-2026-91048</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">Siti Rahmawati</div>
                        <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">Regular Guest</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">Superior Twin</td>
                    <td class="px-6 py-4 text-slate-600">Near Elevator, Early Arrival (11:00)</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-sky-100 text-sky-800 px-2 py-1 font-mono font-bold text-xs">Kamar 204 (Skor 88)</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" class="inline-flex items-center rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                            Konfirmasi Alokasi
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
