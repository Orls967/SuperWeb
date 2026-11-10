@extends('layouts.app')

@section('title', 'Analitik Pencarian & Reindexing — Superweb Enterprise')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    {{-- Header Halaman --}}
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-700/60 pb-5">
        <div>
            <div class="flex items-center space-x-3">
                <div class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center space-x-3">
                        <span>Analitik &amp; Tata Kelola Pencarian Global</span>
                        <x-ds.badge variant="primary" size="sm">Fase 194</x-ds.badge>
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Audit telemetri kueri anti-IDOR, ranking BM25, deteksi 0-result, dan State Machine reindexing lintas 14 modul ekosistem.
                    </p>
                </div>
            </div>
        </div>

        {{-- Tombol Pemicu Reindex Cepat --}}
        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <form method="POST" action="{{ route('core.search.reindex') }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Picu Reindexing Penuh
                </button>
            </form>
        </div>
    </div>

    {{-- Pesan Notifikasi Sukses / Galat --}}
    @if(session('success'))
    <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-4 flex items-center space-x-3 text-emerald-400">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <div class="text-sm font-medium">{{ session('success') }}</div>
    </div>
    @endif

    {{-- Kartu Metrik Telemetri Utama --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Kueri Pencarian</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ number_format($totalQueries) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Dalam {{ $days }} hari terakhir</p>
                </div>
                <div class="p-3 bg-blue-500/10 rounded-xl border border-blue-500/20 text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>

        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-amber-400 uppercase tracking-wider">Kueri 0-Results</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ number_format($zeroResultQueries) }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ $totalQueries > 0 ? round(($zeroResultQueries / $totalQueries) * 100, 1) : 0 }}% dari total kueri
                    </p>
                </div>
                <div class="p-3 bg-amber-500/10 rounded-xl border border-amber-500/20 text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>

        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-emerald-400 uppercase tracking-wider">Rata-rata Latensi</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $avgLatency }} <span class="text-sm font-normal text-slate-400">ms</span></p>
                    <p class="mt-1 text-xs text-emerald-400">Target SLA &lt; 50ms</p>
                </div>
                <div class="p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-emerald-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>

        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Dokumen Terindeks</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ number_format($totalDocuments) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Lintas {{ count($modulesDistribution) }} modul</p>
                </div>
                <div class="p-3 bg-indigo-500/10 rounded-xl border border-indigo-500/20 text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>
    </div>

    {{-- Distribusi Dokumen per Modul --}}
    <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wider mb-4 flex items-center space-x-2">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <span>Cakupan Dokumen per Modul Ekosistem</span>
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
            @forelse($modulesDistribution as $mod => $cnt)
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3 text-center">
                <span class="text-xs text-slate-400 block truncate font-medium">{{ $mod }}</span>
                <span class="text-lg font-bold text-white block mt-1">{{ number_format($cnt) }}</span>
            </div>
            @empty
            <div class="col-span-full text-center py-4 text-xs text-slate-500">
                Belum ada dokumen yang terindeks. Jalankan reindex untuk membangun posting list awal.
            </div>
            @endforelse
        </div>
    </x-ds.card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Kueri Terpopuler --}}
        <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wider border-b border-slate-700 pb-3 flex items-center justify-between">
                <span>10 Kueri Paling Populer (PII Redacted)</span>
                <span class="text-xs font-normal text-slate-400">Frekuensi</span>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700/60">
                            <th class="pb-2 font-semibold">Kueri</th>
                            <th class="pb-2 font-semibold text-center">Hasil</th>
                            <th class="pb-2 font-semibold text-right">Latensi</th>
                            <th class="pb-2 font-semibold text-right">Hit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($topQueries as $q)
                        <tr class="hover:bg-slate-800/40">
                            <td class="py-2.5 font-medium text-slate-200">{{ $q->redacted_query }}</td>
                            <td class="py-2.5 text-center text-slate-400">{{ round((float) $q->avg_results, 1) }}</td>
                            <td class="py-2.5 text-right text-emerald-400 font-mono">{{ round((float) $q->latency, 1) }}ms</td>
                            <td class="py-2.5 text-right font-bold text-white">{{ $q->count }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-slate-500">Belum ada telemetri kueri tercatat.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ds.card>

        {{-- Kueri Tanpa Hasil (0-Results) --}}
        <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-amber-400 uppercase tracking-wider border-b border-slate-700 pb-3 flex items-center justify-between">
                <span>Kueri 0-Results (Butuh Intervensi)</span>
                <span class="text-xs font-normal text-slate-400">Peluang Konten</span>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-700/60">
                            <th class="pb-2 font-semibold">Kueri Gagal</th>
                            <th class="pb-2 font-semibold text-center">Frekuensi</th>
                            <th class="pb-2 font-semibold text-right">Terakhir Dicari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($zeroResults as $zr)
                        <tr class="hover:bg-slate-800/40">
                            <td class="py-2.5 font-medium text-rose-300">{{ $zr->redacted_query }}</td>
                            <td class="py-2.5 text-center font-bold text-white">{{ $zr->count }}</td>
                            <td class="py-2.5 text-right text-slate-400">{{ \Carbon\Carbon::parse($zr->last_seen)->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-6 text-center text-emerald-400 font-medium">Luar biasa! Tidak ada kueri tanpa hasil pada rentang ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ds.card>
    </div>

    {{-- Riwayat Pekerjaan Indexing (State Machine 8 Status) --}}
    <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wider border-b border-slate-700 pb-3 flex items-center justify-between">
            <span>Riwayat Pekerjaan Reindex (State Machine 8 Status)</span>
            <span class="text-xs text-slate-400 font-normal">Idempotent Pipeline</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-700/60">
                        <th class="pb-2 font-semibold">ID Pekerjaan</th>
                        <th class="pb-2 font-semibold">Tipe Job</th>
                        <th class="pb-2 font-semibold">Modul Target</th>
                        <th class="pb-2 font-semibold">Status State Machine</th>
                        <th class="pb-2 font-semibold text-center">Total Dokumen</th>
                        <th class="pb-2 font-semibold text-center">Diproses</th>
                        <th class="pb-2 font-semibold text-right">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recentJobs as $job)
                    <tr class="hover:bg-slate-800/40">
                        <td class="py-2.5 font-mono text-slate-300">{{ substr($job->id, 0, 8) }}...</td>
                        <td class="py-2.5 text-slate-300 font-medium">{{ $job->job_type }}</td>
                        <td class="py-2.5 text-slate-400">{{ $job->module ?: 'Seluruh Modul' }}</td>
                        <td class="py-2.5">
                            @if($job->status === 'indexed')
                                <x-ds.badge variant="success" size="sm">indexed</x-ds.badge>
                            @elseif($job->status === 'failed')
                                <x-ds.badge variant="danger" size="sm">failed</x-ds.badge>
                            @elseif($job->status === 'degraded')
                                <x-ds.badge variant="warning" size="sm">degraded</x-ds.badge>
                            @elseif(in_array($job->status, ['processing', 'reindexing']))
                                <x-ds.badge variant="primary" size="sm">{{ $job->status }}</x-ds.badge>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">{{ $job->status }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 text-center text-slate-300">{{ $job->records_total }}</td>
                        <td class="py-2.5 text-center font-bold text-white">{{ $job->records_processed }}</td>
                        <td class="py-2.5 text-right text-slate-400">{{ $job->created_at->format('d M H:i:s') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-500">Belum ada riwayat reindexing yang tercatat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ds.card>
</div>
@endsection

