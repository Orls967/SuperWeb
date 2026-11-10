@extends('layouts.app')

@section('title', 'Detail Tugas: ' . $task->title . ' — Superweb Enterprise')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center space-x-2 text-xs text-slate-400">
        <a href="{{ route('core.task-inbox.index') }}" class="hover:text-blue-400 transition-colors">Tugas Saya</a>
        <span>/</span>
        <span class="text-slate-300 font-medium truncate max-w-sm">{{ $task->title }}</span>
    </nav>

    {{-- Header Tugas --}}
    <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-slate-700 text-slate-200">
                        {{ $task->module }}
                    </span>

                    @if($task->status === 'approved')
                        <x-ds.badge variant="success" size="sm">Disetujui</x-ds.badge>
                    @elseif($task->status === 'rejected')
                        <x-ds.badge variant="danger" size="sm">Ditolak</x-ds.badge>
                    @elseif($task->status === 'delegated')
                        <x-ds.badge variant="warning" size="sm">Didelegasikan</x-ds.badge>
                    @elseif($task->status === 'escalated')
                        <x-ds.badge variant="danger" size="sm">Dieskalasi</x-ds.badge>
                    @else
                        <x-ds.badge variant="primary" size="sm">Pending Tindakan</x-ds.badge>
                    @endif

                    @if($task->priority === 'urgent')
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            Urgent SLA
                        </span>
                    @endif

                    @if($task->escalation_level > 0)
                        @include('core::task-inbox.partials.escalation-badge', ['level' => $task->escalation_level])
                    @endif
                </div>

                <h1 class="text-2xl font-bold text-white tracking-tight">
                    {{ $task->title }}
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    ID Tugas: <span class="font-mono text-slate-300">{{ $task->id }}</span> • Dibuat: {{ $task->created_at->format('d M Y H:i') }} ({{ $task->created_at->diffForHumans() }})
                </p>
            </div>

            <div class="flex items-center space-x-3">
                @if($task->action_url)
                    <a href="{{ $task->action_url }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span>Buka Layar Entitas Asli</span>
                        <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Rincian Dokumen & Form Tindakan --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Kartu Deskripsi & Instruksi --}}
            <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
                <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-3">
                    Instruksi &amp; Uraian Tindakan
                </h2>
                <div class="text-sm text-slate-300 leading-relaxed whitespace-pre-line">
                    {{ $task->description ?: 'Tidak ada deskripsi rinci yang disertakan.' }}
                </div>

                {{-- Konteks Data Payload --}}
                @if(!empty($task->payload))
                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                        Data Kontekstual Payload
                    </h3>
                    <div class="bg-slate-900 border border-slate-800 rounded-lg p-3 overflow-x-auto text-xs font-mono text-emerald-400">
                        <pre>{{ json_encode($task->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
                @endif
            </x-ds.card>

            {{-- Formulir Eksekusi Keputusan (Jika Masih Aktif) --}}
            @if(in_array($task->status, ['pending', 'in_progress', 'delegated', 'escalated']))
            <x-ds.card class="bg-slate-800/80 border-slate-700/60 p-6">
                <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-3 mb-4 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Ambil Keputusan Otorisasi</span>
                </h2>

                <form method="POST" action="{{ route('core.task-inbox.action', $task->id) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="action_select" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Pilih Keputusan
                        </label>
                        <select id="action_select" name="action" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="approve">Setujui (Approve) — Menyetujui otorisasi tindakan kerja</option>
                            <option value="reject">Tolak (Reject) — Menolak dan menghentikan pengajuan</option>
                            <option value="delegate">Delegasikan (Delegate) — Alihkan wewenang ke staf lain</option>
                        </select>
                    </div>

                    <div id="delegation_container" class="hidden">
                        <label for="delegatee_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Delegasikan Kepada Pengguna
                        </label>
                        <select id="delegatee_id" name="delegatee_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih Anggota Tim</option>
                            @foreach($delegatableUsers ?? [] as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }}) — {{ $u->email }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Catatan Keputusan / Alasan Justifikasi
                        </label>
                        <textarea id="notes" name="notes" rows="3" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tuliskan justifikasi persetujuan atau catatan perbaikan jika ditolak..."></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-5 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Konfirmasi &amp; Simpan Keputusan
                        </button>
                    </div>
                </form>
            </x-ds.card>
            @endif

            {{-- Riwayat Linimasa Audit Trail Mutasi Status --}}
            <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
                <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-3 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Linimasa Audit Trail &amp; Integritas</span>
                </h2>

                <div class="flow-root mt-4">
                    <ul class="-mb-8">
                        @foreach($task->histories as $idx => $history)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-700" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-slate-700 flex items-center justify-center ring-8 ring-slate-800 text-slate-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-xs text-slate-300 font-medium">
                                                Aksi: <span class="text-white font-semibold uppercase">{{ $history->action }}</span>
                                                @if($history->from_status)
                                                    <span class="text-slate-400">({{ $history->from_status }} &rarr; {{ $history->to_status }})</span>
                                                @endif
                                            </p>
                                            @if($history->notes)
                                                <p class="mt-1 text-xs text-slate-400 bg-slate-900/60 p-2 rounded border border-slate-800">
                                                    {{ $history->notes }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="text-right text-xs whitespace-nowrap text-slate-400">
                                            <time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->format('d M H:i') }}</time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </x-ds.card>
            {{-- Thread Catatan & Diskusi Tim --}}
            @include('core::task-inbox.partials.task-comments-thread')
        </div>

        {{-- Kolom Kanan: Rangkuman Metadata SLA & Tim --}}
        <div class="space-y-6">
            <x-ds.card class="bg-slate-800/60 border-slate-700/60 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-700 pb-2">
                    Parameter Kepatuhan SLA
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block">Status Tenggat:</span>
                        @if($task->due_at)
                            @if($task->due_at < now() && !in_array($task->status, ['approved', 'rejected']))
                                <span class="text-rose-400 font-bold flex items-center space-x-1 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Melewati Batas ({{ $task->due_at->diffForHumans() }})</span>
                                </span>
                            @else
                                <span class="text-slate-200 font-medium block mt-0.5">{{ $task->due_at->format('d F Y, H:i') }}</span>
                                <span class="text-slate-400 block">{{ $task->due_at->diffForHumans() }}</span>
                            @endif
                        @else
                            <span class="text-slate-400">Tidak ada batas waktu absolut</span>
                        @endif
                    </div>

                    <div>
                        <span class="text-slate-400 block">Tingkat Eskalasi:</span>
                        <span class="text-slate-200 font-semibold block mt-0.5">Level {{ $task->escalation_level }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block">Wewenang Eksekusi:</span>
                        <span class="text-slate-200 font-mono block mt-0.5">{{ $task->assignee_role ?: 'Semua Pengguna Terotorisasi' }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block">Entitas Asal:</span>
                        <span class="text-slate-200 font-medium block mt-0.5">{{ $task->source_entity_type }}</span>
                        <span class="text-slate-400 font-mono text-[10px] block">{{ $task->source_entity_id }}</span>
                    </div>
                </div>
            </x-ds.card>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const actionSelect = document.getElementById('action_select');
        const delegationContainer = document.getElementById('delegation_container');

        if (actionSelect && delegationContainer) {
            actionSelect.addEventListener('change', function () {
                if (this.value === 'delegate') {
                    delegationContainer.classList.remove('hidden');
                } else {
                    delegationContainer.classList.add('hidden');
                }
            });
        }
    });
</script>
@endsection
