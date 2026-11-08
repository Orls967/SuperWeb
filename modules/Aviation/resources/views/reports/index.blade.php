@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="border-b border-slate-800 pb-5">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                Aviation Audit & Compliance Reporting
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Laporan & Audit Aviasi</h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Ekspor berkas CSV audit penerbangan, rekonsiliasi pemesanan tiket, dan pelaporan refund PM 89/2015.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Flights CSV --}}
            <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <span class="text-3xl block mb-2">🛫</span>
                    <h3 class="text-base font-bold text-white">Laporan Penerbangan</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Daftar jadwal seluruh penerbangan, status operasi, dan konsumsi bahan bakar avtur.
                    </p>
                    <span class="text-xs text-slate-500 block mt-2">Total Data: {{ $stats['total_flights'] ?? 0 }}</span>
                </div>
                <div class="mt-4">
                    <a href="{{ route('aviation.reports.export', ['type' => 'flights']) }}" class="block text-center py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-colors">
                        Unduh CSV &darr;
                    </a>
                </div>
            </div>

            {{-- Tickets CSV --}}
            <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <span class="text-3xl block mb-2">🎫</span>
                    <h3 class="text-base font-bold text-white">Laporan Tiket Terjual</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Rincian nomor tiket, kelas kabin, tarif dasar, PPN/pajak bandara, dan status pemesanan.
                    </p>
                    <span class="text-xs text-slate-500 block mt-2">Total Data: {{ $stats['total_tickets'] ?? 0 }}</span>
                </div>
                <div class="mt-4">
                    <a href="{{ route('aviation.reports.export', ['type' => 'tickets']) }}" class="block text-center py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-colors">
                        Unduh CSV &darr;
                    </a>
                </div>
            </div>

            {{-- Refunds CSV --}}
            <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <span class="text-3xl block mb-2">💸</span>
                    <h3 class="text-base font-bold text-white">Laporan Refund PM 89</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Rekap pengembalian dana tiket, potongan biaya administrasi, dan alasan refund.
                    </p>
                    <span class="text-xs text-slate-500 block mt-2">Total Data: {{ $stats['total_refunds'] ?? 0 }}</span>
                </div>
                <div class="mt-4">
                    <a href="{{ route('aviation.reports.export', ['type' => 'refunds']) }}" class="block text-center py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-colors">
                        Unduh CSV &darr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

