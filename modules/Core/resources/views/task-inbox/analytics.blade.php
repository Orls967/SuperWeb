@extends('layouts.app')

@section('title', 'Analitik Performa & Kepatuhan SLA Tugas — Superweb Enterprise')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    {{-- Header Halaman --}}
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-700/60 pb-5">
        <div>
            <div class="flex items-center space-x-3">
                <div class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center space-x-3">
                        <span>Analitik SLA &amp; Kinerja Tugas</span>
                        <x-ds.badge variant="success" size="sm">Audit Ready</x-ds.badge>
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Evaluasi kepatuhan Service Level Agreement (SLA), waktu tanggap otorisasi, dan distribusi beban kerja per divisi operasional.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <a href="{{ route('core.task-inbox.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Kotak Masuk
            </a>
        </div>
    </div>

    {{-- Kartu Metrik SLA Utama --}}
    @include('core::task-inbox.partials.sla-metrics-card')

    {{-- Matriks Distribusi Peran & Prioritas --}}
    @include('core::task-inbox.partials.workload-matrix-table')

    {{-- Panduan Kebijakan Eskalasi --}}
    <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wider border-b border-slate-700 pb-3 flex items-center space-x-2">
            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Protokol Eskalasi Bertingkat Otomatis (Multi-Tier Policy)</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                    LEVEL 0: TIKET AWAL
                </span>
                <h3 class="text-sm font-bold text-white">Staf Spesialis</h3>
                <p class="text-slate-400">
                    Tugas diterbitkan dan dialokasikan ke pengguna target berdasarkan keahlian teknis modul asal.
                </p>
                <div class="text-[11px] font-mono text-slate-500 pt-1">
                    Batas SLA: 4 - 48 Jam
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    LEVEL 1: SUPERVISOR
                </span>
                <h3 class="text-sm font-bold text-white">Supervisor Modul</h3>
                <p class="text-slate-400">
                    Eskalasi otomatis dipicu jika tugas urgent belum diproses dalam 50% sisa tenggat waktu.
                </p>
                <div class="text-[11px] font-mono text-amber-400/80 pt-1">
                    Peringatan Broadcast &amp; Alert
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-500/20 text-orange-300 border border-orange-500/30">
                    LEVEL 2: MANAJER
                </span>
                <h3 class="text-sm font-bold text-white">Manajer Departemen</h3>
                <p class="text-slate-400">
                    Pelanggaran batas SLA memicu pendelegasian darurat wewenang otorisasi ke tingkat pimpinan divisi.
                </p>
                <div class="text-[11px] font-mono text-orange-400/80 pt-1">
                    Audit Penalty Multiplier
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 space-y-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                    LEVEL 3 &amp; 4: DIREKSI
                </span>
                <h3 class="text-sm font-bold text-white">Direksi / Board</h3>
                <p class="text-slate-400">
                    Eskalasi tingkat tertinggi untuk dokumen strategis bernilai tinggi atau keterlambatan berdampak sistemik.
                </p>
                <div class="text-[11px] font-mono text-rose-400/80 pt-1">
                    Executive Intervention
                </div>
            </div>
        </div>
    </x-ds.card>
</div>
@endsection

