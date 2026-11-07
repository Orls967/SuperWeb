@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Hub Transportasi & Layanan Parkir Valet (Valet Parking Hub)</h1>
            <p class="text-sm text-slate-500">Pencatatan kendaraan tamu hotel, penomoran tiket valet fisik, dan pemanggilan mobil penjemputan bandara.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Terbitkan Tiket Valet Masuk
            </button>
        </div>
    </div>

    <!-- Metrik Operasional Parkir Valet -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kendaraan Tamu Parkir</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">54 Mobil</div>
            <p class="text-xs text-slate-500 mt-1">Area Basement B1 & B2</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Permintaan Ambil Kendaraan</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">3 Antrean</div>
            <p class="text-xs text-slate-500 mt-1">Waktu tunggu rata-rata 4.5 menit</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Armada Shuttle Bandara Siaga</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">4 HiAce VIP</div>
            <p class="text-xs text-slate-500 mt-1">Terintegrasi sistem Logistics G7</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kapasitas Slot Parkir Tersisa</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">26 Slot Kosong</div>
            <p class="text-xs text-slate-500 mt-1">Cukup untuk tamu acara malam</p>
        </div>
    </div>
</div>
@endsection
