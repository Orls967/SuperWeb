@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Gerbang Masuk Restoran Sarapan (Breakfast Governor Terminal)</h1>
            <p class="text-sm text-slate-500">Validasi nomor kamar tamu untuk hak kupon sarapan prasmanan pagi (anti double-entry dining).</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                Pindai Kunci Kamar Tamu
            </button>
        </div>
    </div>

    <!-- Ringkasan Real-Time Tamu Sarapan -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Tamu Terlayani Sarapan</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">142 Pax</div>
            <p class="text-xs text-slate-500 mt-1">Dari total 180 hak sarapan hari ini</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kapasitas Tempat Duduk Resto</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">85% Terisi</div>
            <p class="text-xs text-slate-500 mt-1">Sisa 18 meja kosong</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Peringatan Akses Ganda (Ditolak)</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">1 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Kamar 405 kupon sarapan telah terpakai</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Waktu Puncak Kunjungan</span>
            <div class="mt-2 text-2xl font-bold text-slate-800">07:30 - 08:45</div>
            <p class="text-xs text-slate-500 mt-1">Alur prasmanan berjalan lancar</p>
        </div>
    </div>
</div>
@endsection
