@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Audit Diskrepansi Kamar: Sleepers & Skips</h1>
            <p class="text-sm text-slate-500">Pencocokan status fisik kamar riil dari laporan tata graha vs status reservasi sistem PMS.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Pindai Seluruh Lantai Sekarang
            </button>
        </div>
    </div>

    <!-- Ringkasan Diskrepansi Status Kamar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Total Kamar Diaudit</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">100 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Laporan harian floor supervisor</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Sleepers (Sistem Isi, Fisik Kosong)</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">0 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Tidak ada tagihan tertahan</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Skips (Sistem Kosong, Fisik Terisi)</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">0 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Tidak ada okupansi liar tanpa tiket</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Integritas Inventori Kamar</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">100.0%</div>
            <p class="text-xs text-slate-500 mt-1">Audit fisik seimbang sempurna</p>
        </div>
    </div>
</div>
@endsection
