@extends('layouts.app')

@section('title', 'Kotak Masuk Tugas Saya — Superweb Enterprise')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    {{-- Header Halaman --}}
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-700/60 pb-5">
        <div>
            <div class="flex items-center space-x-3">
                <div class="p-2.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center space-x-3">
                        <span>Kotak Masuk Tugas Saya</span>
                        <x-ds.badge variant="primary" size="sm">§19.1 Enterprise</x-ds.badge>
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Pusat tindakan kerja terintegrasi: persetujuan otorisasi, review operasional, delegasi wewenang, dan pemantauan kepatuhan SLA.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            @if(auth()->user()?->role === 'admin')
            <a href="{{ route('core.search.analytics') }}" class="inline-flex items-center px-3.5 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Analitik & Reindex
            </a>
            @endif
            <button type="button" onclick="window.location.reload()" class="inline-flex items-center px-3.5 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Segarkan Antrean
            </button>
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

    {{-- Metrik KPI Ringkasan Beban Tugas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Tugas Pending</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $summary['pending_count'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-400">Memerlukan evaluasi langsung</p>
                </div>
                <div class="p-3 bg-blue-500/10 rounded-xl border border-blue-500/20 text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>

        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-amber-400 uppercase tracking-wider">Prioritas Urgent</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $summary['urgent_count'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-400">SLA kritis &lt; 4 jam</p>
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
                    <p class="text-xs font-medium text-rose-400 uppercase tracking-wider">Melewati Tenggat (Overdue)</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $summary['overdue_count'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-rose-400/80">Pelanggaran batas SLA</p>
                </div>
                <div class="p-3 bg-rose-500/10 rounded-xl border border-rose-500/20 text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>

        <x-ds.card class="bg-slate-800/60 border-slate-700/60">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-purple-400 uppercase tracking-wider">Tingkat Eskalasi Aktif</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $summary['escalated_count'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-400">Diteruskan ke hirarki atas</p>
                </div>
                <div class="p-3 bg-purple-500/10 rounded-xl border border-purple-500/20 text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
        </x-ds.card>
    </div>

    {{-- Filter Panel --}}
    @include('core::task-inbox.partials.task-filter')

    {{-- Daftar Tugas --}}
    <x-ds.card class="bg-slate-800/40 border-slate-700/60 divide-y divide-slate-700/50">
        <div class="p-4 sm:px-6 bg-slate-800/80 rounded-t-xl flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center space-x-2">
                <span>Daftar Tindakan Kerja</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">
                    {{ $tasks->total() }} entri
                </span>
            </h2>
            <div class="text-xs text-slate-400">
                Diurutkan berdasarkan tenggat waktu terdekat &amp; prioritas
            </div>
        </div>

        @forelse($tasks as $task)
            @include('core::task-inbox.partials.task-card', ['task' => $task])
        @empty
            <div class="py-16 text-center">
                <div class="mx-auto w-12 h-12 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-white">Tidak Ada Tugas Tertunda</h3>
                <p class="mt-1 text-xs text-slate-400 max-w-sm mx-auto">
                    Semua antrean tindakan kerja Anda telah selesai diproses. Periksa kembali secara berkala saat mutasi baru masuk.
                </p>
            </div>
        @endforelse

        {{-- Pagination --}}
        @if($tasks->hasPages())
        <div class="p-4 sm:px-6 bg-slate-800/40">
            {{ $tasks->appends($filters)->links() }}
        </div>
        @endif
    </x-ds.card>
</div>
@endsection

