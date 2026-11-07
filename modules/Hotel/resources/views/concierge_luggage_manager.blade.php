@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manajemen Penitipan Bagasi & Bellhop (Concierge Luggage)</h1>
            <p class="text-sm text-slate-500">Pencatatan koper tamu early check-in, penitipan pasca checkout, dan pengiriman bagasi ke kamar.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Terbitkan Tag Penitipan Baru
            </button>
        </div>
    </div>

    <!-- Ringkasan Bagasi Tersimpan -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Bagasi Aktif Tersimpan</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">38 Koper</div>
            <p class="text-xs text-slate-500 mt-1">Area Luggage Room A & B</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Tamu Menunggu Diantar ke Kamar</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">6 Antrean</div>
            <p class="text-xs text-slate-500 mt-1">Kamar baru saja siap diinspeksi</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Rata-Rata Waktu Antar</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">8.2 Menit</div>
            <p class="text-xs text-slate-500 mt-1">Target SLA < 15 menit tercapai</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kapasitas Gudang Bagasi</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">45% Terisi</div>
            <p class="text-xs text-slate-500 mt-1">Sisa ruang 50 koper</p>
        </div>
    </div>

    <!-- Tabel Penitipan Koper Aktif -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kode Tag Bagasi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama Tamu & Kontak</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Jumlah Koli</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Target Kamar</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Lokasi Rak</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">LUG-0412-881A</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">Robert Tanaka</div>
                        <div class="text-xs text-slate-500">+62 812-9988-7766</div>
                    </td>
                    <td class="px-6 py-4 text-center font-bold text-slate-800">2 Koper</td>
                    <td class="px-6 py-4 text-slate-700">Kamar 412 (Sudah Inspected)</td>
                    <td class="px-6 py-4 text-center font-mono text-slate-600">BAY_B_RACK_04</td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" class="inline-flex items-center rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                            Antar ke Kamar
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
