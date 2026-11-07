@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Laporan Kilat Kinerja Harian GM (Daily Flash Report)</h1>
            <p class="text-sm text-slate-500">Ringkasan eksekutif okupansi, pendapatan kamar, ADR, RevPAR, dan perbandingan target MTD.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Cetak PDF Laporan Direksi
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Kirim Flash Report via Email
            </button>
        </div>
    </div>

    <!-- Indikator Kunci Kinerja Harian -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Tingkat Okupansi Harian</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">86.4%</div>
            <p class="text-xs text-slate-500 mt-1">+4.2% vs target anggaran</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Average Daily Rate (ADR)</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">Rp 1.285.000</div>
            <p class="text-xs text-slate-500 mt-1">+6.5% vs periode tahun lalu</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Revenue Per Available Room (RevPAR)</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">Rp 1.110.240</div>
            <p class="text-xs text-slate-500 mt-1">Yield indeks berada di atas pasar</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Total Pendapatan Kotor Harian</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">Rp 148.650.000</div>
            <p class="text-xs text-slate-500 mt-1">Termasuk Kamar, F&B, dan Spa</p>
        </div>
    </div>
</div>
@endsection
