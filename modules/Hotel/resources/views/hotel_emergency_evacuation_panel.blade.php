@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Panel Kontrol Evakuasi & Keamanan Gedung Hotel</h1>
            <p class="text-sm text-slate-500">Integrasi sistem fire alarm, pelepasan kunci darurat turnstile, dan panduan titik kumpul tamu.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500">
                Pemicu Darurat / Buka Semua Pintu Turnstile
            </button>
        </div>
    </div>

    <!-- Status Sistem Keselamatan Gedung -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Status Sistem Alarm Kebakaran</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">NORMAL (STANDBY)</div>
            <p class="text-xs text-slate-500 mt-1">Seluruh sensor asap kamar aktif</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pintu Darurat & Tangga Evakuasi</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">12 Titik Bebas Hambatan</div>
            <p class="text-xs text-slate-500 mt-1">Tekanan udara tangga darurat normal</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Turnstile Fail-Safe Mode</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">AUTO_UNLOCK_READY</div>
            <p class="text-xs text-slate-500 mt-1">Buka bebas tanpa hambatan saat alarm</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Titik Kumpul Utama (Muster Point)</span>
            <div class="mt-2 text-2xl font-bold text-slate-800">Plaza Parkir Barat</div>
            <p class="text-xs text-slate-500 mt-1">Kapasitas aman 1.500 orang</p>
        </div>
    </div>
</div>
@endsection
