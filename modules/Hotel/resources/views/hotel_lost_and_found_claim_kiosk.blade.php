@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Kios Mandiri Klaim Barang Tertinggal (Lost & Found Guest Kiosk)</h1>
            <p class="text-sm text-slate-500">Pencarian mandiri oleh tamu atas barang pribadi yang tertinggal di area kamar hotel atau restoran.</p>
        </div>
    </div>

    <!-- Panel Pencarian Mandiri -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-4">Formulir Verifikasi Kepemilikan Barang</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700">Nomor Kamar Saat Menginap</label>
                <input type="text" placeholder="Contoh: 302" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Tanggal Checkout</label>
                <input type="date" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="button" class="w-full inline-flex justify-center items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Cari di Database Brankas
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
