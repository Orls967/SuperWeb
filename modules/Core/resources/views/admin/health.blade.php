@extends('layouts.app')

@section('title', 'Kesehatan & Observabilitas Sistem')
@section('subtitle', 'Monitoring Status Kesehatan 7 Pilar Arsitektur Platform & Audit Trail')

@section('content')
<div class="space-y-8" x-data="{ running: false }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-rose-400 hover:text-rose-300">&times;</button>
        </div>
    @endif

    {{-- Top Header Action & Overall System Status --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800/40 p-5 rounded-2xl border border-slate-700/50 backdrop-blur-xl">
        <div class="flex items-center gap-4">
            <div class="relative">
                <div class="w-12 h-12 rounded-2xl {{ $health['status'] === 'HEALTHY' ? 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/20' : 'bg-gradient-to-br from-rose-500 to-red-600 shadow-rose-500/20' }} flex items-center justify-center text-white shadow-lg">
                    @if($health['status'] === 'HEALTHY')
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    @else
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </div>
                <span class="absolute -top-1 -right-1 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $health['status'] === 'HEALTHY' ? 'bg-emerald-400' : 'bg-rose-400' }} opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 {{ $health['status'] === 'HEALTHY' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                </span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-black text-white">Status Platform:</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider {{ $health['status'] === 'HEALTHY' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                        {{ $health['status'] }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Dipindai pada {{ \Carbon\Carbon::parse($health['timestamp'])->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') }} WIB &bull; Waktu eksekusi: <span class="font-mono text-slate-300">{{ $health['duration_ms'] }} ms</span>
                </p>
            </div>
        </div>

        <form action="{{ route('admin.health.run') }}" method="POST" @submit="running = true">
            @csrf
            <button type="submit" :disabled="running" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-500/25 transition flex items-center gap-2 disabled:opacity-50">
                <svg x-show="!running" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg x-show="running" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span x-text="running ? 'Memindai Seluruh Sistem...' : 'Jalankan Diagnosa Sekarang'"></span>
            </button>
        </form>
    </div>

    {{-- 7 Pillars Diagnostics Grid --}}
    <div>
        <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            Hasil Pemeriksaan 7 Pilar Platform
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($health['checks'] as $key => $check)
                <div class="rounded-2xl bg-gradient-to-br from-slate-800/80 to-slate-900/80 border border-slate-700/60 p-5 backdrop-blur-xl shadow-lg hover:border-slate-600 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ $check['name'] }}</span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $check['ok'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $check['ok'] ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                                {{ $check['status'] }}
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-white mt-1 leading-snug">
                            {{ $check['message'] }}
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-700/40 flex items-center justify-between text-xs text-slate-500">
                        <span class="font-mono text-[11px]">Subsystem: {{ $key }}</span>
                        @if(isset($check['latency_ms']))
                            <span class="font-mono text-slate-400 text-[11px]">{{ $check['latency_ms'] }} ms</span>
                        @else
                            <span class="text-emerald-400 text-[11px] font-semibold">Integrity Verified</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- System Audit Log Activity --}}
    <div class="rounded-2xl bg-slate-800/40 border border-slate-700/50 p-6 backdrop-blur-xl shadow-xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Jejak Rekam Audit Sistem (Audit Log Trail)
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Riwayat aktivitas diagnostik dan operasional platform yang tersimpan secara append-only</p>
            </div>
            <span class="text-xs font-mono text-slate-400 bg-slate-900/60 px-3 py-1 rounded-lg border border-slate-700/60">
                15 Entri Terakhir
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-900/50 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-700/60">
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Aksi / Event</th>
                        <th class="py-3 px-4">Pengguna</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-4">Ringkasan Konteks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @forelse($recentAuditLogs as $log)
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="py-3 px-4 font-mono text-slate-400 whitespace-nowrap">
                                {{ $log->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-800 text-indigo-300 border border-slate-700">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-medium text-white">
                                {{ $log->user ? $log->user->name : 'System / Cron' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-400">
                                {{ $log->ip_address ?: '127.0.0.1' }}
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate text-slate-400 font-mono text-[11px]">
                                @if($log->context)
                                    {{ json_encode($log->context) }}
                                @else
                                    <span class="text-slate-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500 italic">
                                Belum ada catatan audit log tersimpan di database.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
