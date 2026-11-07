@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pusat Audit Minibar & Biaya Insidental Kamar</h1>
            <p class="text-sm text-slate-500">Pencatatan konsumsi camilan/minuman minibar dan binatu ekspres langsung ke folio tamu.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Input Konsumsi Kamar Check-Out
            </button>
        </div>
    </div>

    <!-- Ringkasan Pendapatan Insidental Hari Ini -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pendapatan Minibar Hari Ini</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 4.250.000</div>
            <p class="text-xs text-slate-500 mt-1">Dari 28 kamar check-out</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pendapatan Laundry Tamu</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">Rp 6.180.000</div>
            <p class="text-xs text-slate-500 mt-1">Layanan express & reguler</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pajak PBJT & Service 15%</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">Rp 1.564.500</div>
            <p class="text-xs text-slate-500 mt-1">Otomatis diposting ke ledger</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kamar Menunggu Pengecekan</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">4 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Inspeksi mini-bar sedang berjalan</p>
        </div>
    </div>
</div>
@endsection
