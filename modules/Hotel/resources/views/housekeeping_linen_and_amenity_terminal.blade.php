@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pusat Distribusi Linen & Amenitas Tata Graha (Housekeeping Supply)</h1>
            <p class="text-sm text-slate-500">Pemantauan persediaan handuk bersih, sprei, dental kit, dan perlengkapan kamar per pantry lantai.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Pencatatan Linen Kotor Keluar
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Permintaan Stok Tambahan ke Gudang Sentral
            </button>
        </div>
    </div>

    <!-- Metrik Stok Pantry Lantai -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Handuk Bersih Siap Pakai</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">420 Set</div>
            <p class="text-xs text-slate-500 mt-1">Bath towel, hand towel, bath mat</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Sprei & Bed Cover Bersih</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">280 Pcs</div>
            <p class="text-xs text-slate-500 mt-1">King size & twin bed linen</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Stok Amenitas Gigi & Sabun</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">850 Paket</div>
            <p class="text-xs text-slate-500 mt-1">Cukup untuk kebutuhan 4 hari</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pantry Kritis (< 20%)</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">Pantry Lantai 3</div>
            <p class="text-xs text-slate-500 mt-1">Sisa bath mat 4 pcs</p>
        </div>
    </div>

    <!-- Tabel Monitoring Stok per Lantai -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Lokasi Pantry</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Bed Sheet (Pcs)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Bath Towel (Pcs)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Dental Kit (Pack)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Ketersediaan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-semibold text-slate-900">Pantry Lantai 2 (Kamar 201 - 225)</td>
                    <td class="px-6 py-4 text-center font-mono">65</td>
                    <td class="px-6 py-4 text-center font-mono">90</td>
                    <td class="px-6 py-4 text-center font-mono">140</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">OPTIMAL</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-semibold text-slate-900">Pantry Lantai 3 (Kamar 301 - 325)</td>
                    <td class="px-6 py-4 text-center font-mono">22</td>
                    <td class="px-6 py-4 text-center font-mono">35</td>
                    <td class="px-6 py-4 text-center font-mono">48</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-semibold">PERLU RESTOCK</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
