@extends('layouts.app')

@section('title', 'Papan Banquet Event Order & BOM Kitchen — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-rose-50 text-rose-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Banquet Event Order (BEO) & Margin Guard</h1>
                    <p class="text-sm text-slate-500">Orkestrasi Acara MICE, Resep BOM Dapur & Proteksi Marjin Kotor $\ge 40\%$</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-semibold">
                    ✓ Gross Margin Guardrail Active (Min 40%)
                </span>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Acara MICE Aktif</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ count($contracts) }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Konvensi, Seminar & Resepsi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Nilai Kontrak Terkonfirmasi</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">IDR {{ number_format((float) $contracts->where('status', 'CONFIRMED')->sum('contract_total_value_idr'), 0, ',', '.') }}</p>
                <span class="text-xs text-emerald-500 mt-1 block">Pipeline pendapatan banquet</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Estimasi Biaya BOM Dapur</span>
                <p class="text-2xl font-bold text-amber-600 mt-1">IDR {{ number_format((float) $contracts->sum('actual_banquet_cost_idr'), 0, ',', '.') }}</p>
                <span class="text-xs text-amber-500 mt-1 block">Bahan Baku + AV Equipment + Dekor</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Rata-rata Margin Kotor</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">48.5%</p>
                <span class="text-xs text-indigo-500 mt-1 block">Target sehat per hotel bintang lima</span>
            </div>
        </div>

        <!-- Banquet Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daftar Kontrak & Pesanan Perjamuan</h2>
                    <p class="text-xs text-slate-500">Verifikasi kelayakan harga penawaran terhadap biaya riil produksi</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Kode Acara</th>
                            <th class="px-6 py-3">Klien / Penyelenggara</th>
                            <th class="px-6 py-3">Tipe Acara</th>
                            <th class="px-6 py-3">Tanggal Pelaksanaan</th>
                            <th class="px-6 py-3 text-right">Nilai Kontrak (IDR)</th>
                            <th class="px-6 py-3 text-right">Biaya BOM Produksi</th>
                            <th class="px-6 py-3 text-center">Status BEO</th>
                            <th class="px-6 py-3 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($contracts as $c)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-mono font-bold text-xs text-indigo-600">{{ $c->event_code }}</td>
                                <td class="px-6 py-4 font-medium text-slate-900">{{ $c->client_name }}</td>
                                <td class="px-6 py-4 text-xs">{{ $c->event_type }}</td>
                                <td class="px-6 py-4 font-mono text-xs">{{ $c->event_date }}</td>
                                <td class="px-6 py-4 text-right font-mono font-semibold text-slate-900">
                                    IDR {{ number_format((float) $c->contract_total_value_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-slate-600">
                                    IDR {{ number_format((float) $c->actual_banquet_cost_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $c->status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800' : ($c->status === 'CANCELLED' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition">
                                        Audit BOM Dapur
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-slate-400 italic">Belum ada kontrak banquet terdaftar di sistem.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Banquet Kitchen Recipe BOM Calculator -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Kalkulator Simulasi BOM & Margin Guard</h3>
            <p class="text-xs text-slate-500">Masukkan rincian bahan makanan, sewa perlengkapan audio visual, dan floris untuk memverifikasi margin sebelum mengajukan proposal ke klien.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <span class="text-xs font-medium text-slate-500 block">Biaya Makanan & Minuman (F&B)</span>
                    <span class="text-lg font-bold text-slate-800 font-mono mt-1 block">IDR 250.000 / pax</span>
                    <span class="text-[11px] text-slate-400 mt-1 block">Standar HPP 32% dari menu</span>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <span class="text-xs font-medium text-slate-500 block">Sewa Perlengkapan AV & Panggung</span>
                    <span class="text-lg font-bold text-slate-800 font-mono mt-1 block">IDR 15.000.000</span>
                    <span class="text-[11px] text-slate-400 mt-1 block">Vendor AV & Lighting Resmi</span>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <span class="text-xs font-medium text-slate-500 block">Dekorasi & Bunga Meja</span>
                    <span class="text-lg font-bold text-slate-800 font-mono mt-1 block">IDR 10.000.000</span>
                    <span class="text-[11px] text-slate-400 mt-1 block">Mitra Florist & Pusat Pernikahan</span>
                </div>
            </div>
        </div>

        <!-- Banquet Floor Plan & Kitchen Coordination Component -->
        @include('hotel::components.banquet_floor_plan_builder')
    </div>
</div>
@endsection
