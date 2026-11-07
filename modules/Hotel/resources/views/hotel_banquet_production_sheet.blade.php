@extends('layouts.app')

@section('title', 'Lembar Produksi & Persiapan Dapur Banquet — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-amber-50 text-amber-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Lembar Produksi & Persiapan Dapur Banquet (Kitchen Production Sheet)</h1>
                    <p class="text-sm text-slate-500">Penghitungan Kebutuhan Bahan Baku Bersih, Yield Faktor Dapur, Rasio Staf Perbantuan HCM & Margin Guard</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                    Margin BOM: 48.2% (Aman)
                </span>
                <button class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    Cetak Lembar Produksi Dapur
                </button>
            </div>
        </div>

        <!-- Banquet Captain Order Sheet Component -->
        @include('hotel::components.banquet_captain_order_sheet')

        <!-- Ingredient Raw Materials Breakdown -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Rincian Komponen Bahan Baku Mentah Terjadwal (BOM Explosion)</h2>
                    <p class="text-xs text-slate-500">Kalkulasi otomatis berdasarkan 450 Pax Gala Dinner Bank Rakyat Indonesia</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 bg-amber-100 text-amber-800 rounded">
                    Total HPP Bahan: IDR 78.500.000
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Nama Bahan Baku</th>
                            <th class="px-6 py-3">Kategori</th>
                            <th class="px-6 py-3 text-right">Kebutuhan Netto</th>
                            <th class="px-6 py-3 text-right">Yield Loss (%)</th>
                            <th class="px-6 py-3 text-right">Kebutuhan Kotor Gudang</th>
                            <th class="px-6 py-3 text-right">Estimasi Biaya Satuan</th>
                            <th class="px-6 py-3 text-right font-bold">Subtotal HPP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-bold text-slate-800">Australian Wagyu Beef Tenderloin MB5</td>
                            <td class="px-6 py-4 text-xs">Butchery Daging Segar</td>
                            <td class="px-6 py-4 text-right font-mono">90.0 Kg</td>
                            <td class="px-6 py-4 text-right font-mono text-amber-600">12.0%</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">102.3 Kg</td>
                            <td class="px-6 py-4 text-right font-mono">IDR 480.000 / Kg</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 49.104.000</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-bold text-slate-800">Black Truffle Paste & Butter Sauce</td>
                            <td class="px-6 py-4 text-xs">Saus & Bumbu Impor</td>
                            <td class="px-6 py-4 text-right font-mono">15.0 Kg</td>
                            <td class="px-6 py-4 text-right font-mono text-slate-400">2.0%</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">15.3 Kg</td>
                            <td class="px-6 py-4 text-right font-mono">IDR 650.000 / Kg</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 9.945.000</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-bold text-slate-800">Organic Baby Vegetables & Asparagus</td>
                            <td class="px-6 py-4 text-xs">Hasil Kebun Lokal Mitra</td>
                            <td class="px-6 py-4 text-right font-mono">45.0 Kg</td>
                            <td class="px-6 py-4 text-right font-mono text-amber-600">8.0%</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">48.9 Kg</td>
                            <td class="px-6 py-4 text-right font-mono">IDR 85.000 / Kg</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 4.156.500</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
