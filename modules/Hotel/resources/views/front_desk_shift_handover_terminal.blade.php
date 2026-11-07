@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Terminal Serah Terima Shift Front Desk (Shift Handover)</h1>
            <p class="text-sm text-slate-500">Rekonsiliasi kas kasir resepsionis, serah terima kunci fisik master, dan catatan operasional antar-shift.</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                Cetak Lembar Kasir Shift
            </button>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Tutup Shift & Tanda Tangan Digital
            </button>
        </div>
    </div>

    <!-- Ringkasan Kas & Penerimaan Shift -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kas Fisik di Laci (Cash Float)</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">Rp 5.000.000</div>
            <p class="text-xs text-slate-500 mt-1">Modal awal shift pagi</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Penerimaan Tunai Shift Ini</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 12.450.000</div>
            <p class="text-xs text-slate-500 mt-1">Pembayaran folio & deposit tunai</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Penerimaan Kartu / QRIS</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">Rp 38.920.000</div>
            <p class="text-xs text-slate-500 mt-1">Settlement EDC perbankan cocok</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Sisa Tamu Menunggu Check-In</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">8 Kamar</div>
            <p class="text-xs text-slate-500 mt-1">Diserahkan ke Shift Malam</p>
        </div>
    </div>

    <!-- Catatan Khusus untuk Shift Berikutnya -->
    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900">Catatan Operasional & Kejadian Penting Shift</h2>
        <div class="space-y-3 text-sm">
            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-900 flex items-start space-x-3">
                <span class="font-bold font-mono">14:30</span>
                <div>Tamu Kamar 504 (Bpk. Hendra) meminta wake-up call besok pagi pukul 04:30 untuk penerbangan pagi. Taksi bandara telah dijadwalkan via Logistics.</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-slate-800 flex items-start space-x-3">
                <span class="font-bold font-mono">15:15</span>
                <div>Kamar 208 mengalami perbaikan shower oleh tim Engineering, estimasi selesai pukul 18:00 dan siap diinspeksi.</div>
            </div>
        </div>
    </div>
</div>
@endsection
