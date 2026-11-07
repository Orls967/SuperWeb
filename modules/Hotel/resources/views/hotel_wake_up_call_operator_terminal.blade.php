@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Terminal Panggilan Bangun Tamu (Wake-Up Call PBX Console)</h1>
            <p class="text-sm text-slate-500">Jadwal alarm bangun tidur otomatis terhubung ke sistem telepon kamar dan asisten pintar kamar.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Jadwalkan Panggilan Baru
            </button>
        </div>
    </div>

    <!-- Ringkasan Jadwal Panggilan Besok Pagi -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Jadwal Panggilan Aktif</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">12 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Antara pukul 04:00 - 07:00 WITA</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Panggilan Pagi Ini Sukses</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">100%</div>
            <p class="text-xs text-slate-500 mt-1">Tamu menjawab panggilan telepon</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Integrasi PBX VOIP</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">ONLINE</div>
            <p class="text-xs text-slate-500 mt-1">SIP Trunk Telephony aktif</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Peringatan Tidak Diangkat (SLA)</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">0 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Duty Manager siaga ketuk pintu</p>
        </div>
    </div>
</div>
@endsection
