@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Kios Informasi & Asisten Wisata Tamu (Digital Concierge Kiosk)</h1>
            <p class="text-sm text-slate-500">Pemandu atraksi wisata lokal, jadwal shuttle bus bandara, pemesanan restoran, dan rekomendasi aktivitas.</p>
        </div>
    </div>

    <!-- Pilihan Cepat Kategori Layanan Wisata -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm hover:border-indigo-500 transition cursor-pointer">
            <div class="text-lg font-bold text-slate-900">Jadwal Shuttle Bus Bandara</div>
            <p class="text-sm text-slate-500 mt-2">Keberangkatan setiap 60 menit menuju Bandara Internasional I Gusti Ngurah Rai (DPS).</p>
            <div class="mt-4 text-xs font-semibold text-indigo-600 uppercase">Lihat Jadwal Lengkap &rarr;</div>
        </div>
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm hover:border-indigo-500 transition cursor-pointer">
            <div class="text-lg font-bold text-slate-900">Reservasi Meja Restoran & Sunset Lounge</div>
            <p class="text-sm text-slate-500 mt-2">Pemesanan meja makan malam prasmanan seafood dan koktail tepi pantai.</p>
            <div class="mt-4 text-xs font-semibold text-indigo-600 uppercase">Pesan Meja Sekarang &rarr;</div>
        </div>
        <div class="bg-white p-6 rounded-lg border border-slate-200 shadow-sm hover:border-indigo-500 transition cursor-pointer">
            <div class="text-lg font-bold text-slate-900">Tur Budaya & Paket Destinasi Lokal</div>
            <p class="text-sm text-slate-500 mt-2">Kunjungan Pura Uluwatu, Tari Kecak, dan wisata kuliner Jimbaran terintegrasi.</p>
            <div class="mt-4 text-xs font-semibold text-indigo-600 uppercase">Eksplorasi Paket &rarr;</div>
        </div>
    </div>
</div>
@endsection
