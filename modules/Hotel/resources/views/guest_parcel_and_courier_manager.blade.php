@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manajemen Paket & Kiriman Tamu (Guest Courier Parcels)</h1>
            <p class="text-sm text-slate-500">Penerimaan paket belanja online tamu in-house dari kurir ekspedisi (JNE, SiCepat, DHL, FedEx).</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Terima Kiriman Paket Baru
            </button>
        </div>
    </div>

    <!-- Ringkasan Paket Tamu -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Paket Menunggu Diserahkan</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">14 Paket</div>
            <p class="text-xs text-slate-500 mt-1">Tersimpan di rak concierge</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Paket Makanan Segar / Dingin</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">3 Paket</div>
            <p class="text-xs text-slate-500 mt-1">Disimpan di lemari pendingin</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Paket Diserahkan Hari Ini</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">22 Paket</div>
            <p class="text-xs text-slate-500 mt-1">Tanda tangan digital tamu lengkap</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Notifikasi WhatsApp Terkirim</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">100%</div>
            <p class="text-xs text-slate-500 mt-1">Pemberitahuan otomatis ke tamu</p>
        </div>
    </div>

    <!-- Tabel Daftar Paket Menunggu Diambil -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kode Tracking Internal</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama Tamu & Kamar</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Ekspedisi & No. Resi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Deskripsi Isi</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">PRC-1108-9921A</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">Dr. Sarah Connor</div>
                        <div class="text-xs text-slate-500">Kamar 501 (Executive Suite)</div>
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        <span class="font-semibold">DHL Express</span>
                        <div class="font-mono text-xs text-slate-500">AWB-883920194</div>
                    </td>
                    <td class="px-6 py-4 text-slate-600">Dokumen Bisnis & Sampel Medis (Pendingin)</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-sky-100 text-sky-800 px-2 py-0.5 text-xs font-semibold">CHILLED_STORAGE</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" class="inline-flex items-center rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                            Serahkan ke Tamu
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
