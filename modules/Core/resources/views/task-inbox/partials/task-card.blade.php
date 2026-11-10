<div class="p-4 sm:p-6 hover:bg-slate-800/60 transition-colors flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    {{-- Info Utama Tugas --}}
    <div class="flex items-start space-x-4 min-w-0 flex-1">
        {{-- Ikon Prioritas --}}
        <div class="mt-1 flex-shrink-0">
            @if($task->priority === 'urgent')
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400" title="Prioritas Urgent">
                    <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            @elseif($task->priority === 'high')
                <div class="w-9 h-9 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400" title="Prioritas Tinggi">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            @else
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-400" title="Prioritas Normal">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            @endif
        </div>

        {{-- Konten Teks --}}
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <a href="{{ route('core.task-inbox.show', $task->id) }}" class="text-base font-semibold text-white hover:text-blue-400 transition-colors truncate">
                    {{ $task->title }}
                </a>

                {{-- Badge Modul --}}
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-700 text-slate-300">
                    {{ $task->module }}
                </span>

                {{-- Badge Status --}}
                @if($task->status === 'approved')
                    <x-ds.badge variant="success" size="sm">Disetujui</x-ds.badge>
                @elseif($task->status === 'rejected')
                    <x-ds.badge variant="danger" size="sm">Ditolak</x-ds.badge>
                @elseif($task->status === 'delegated')
                    <x-ds.badge variant="warning" size="sm">Didelegasikan</x-ds.badge>
                @elseif($task->status === 'escalated')
                    <x-ds.badge variant="danger" size="sm">Dieskalasi</x-ds.badge>
                @else
                    <x-ds.badge variant="primary" size="sm">Menunggu Aksi</x-ds.badge>
                @endif

                {{-- Indikator Eskalasi --}}
                @if($task->escalation_level > 0)
                    @include('core::task-inbox.partials.escalation-badge', ['level' => $task->escalation_level])
                @endif
            </div>

            @if(!empty($task->description))
                <p class="text-xs text-slate-400 line-clamp-2 mb-2">
                    {{ $task->description }}
                </p>
            @endif

            {{-- Metadata Pelengkap --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                <div class="flex items-center space-x-1">
                    <span class="text-slate-400">Entitas:</span>
                    <span class="text-slate-300 font-medium">{{ $task->source_entity_type }} ({{ $task->source_entity_id }})</span>
                </div>

                @if($task->due_at)
                    <div class="flex items-center space-x-1">
                        <span class="text-slate-400">Tenggat SLA:</span>
                        @if($task->due_at < now() && !in_array($task->status, ['approved', 'rejected']))
                            <span class="text-rose-400 font-semibold flex items-center space-x-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Terlambat ({{ $task->due_at->diffForHumans() }})</span>
                            </span>
                        @else
                            <span class="text-slate-300">{{ $task->due_at->format('d M Y H:i') }} ({{ $task->due_at->diffForHumans() }})</span>
                        @endif
                    </div>
                @endif

                @if($task->assignee_role)
                    <div class="flex items-center space-x-1">
                        <span class="text-slate-400">Wewenang:</span>
                        <span class="text-slate-300 font-mono">{{ $task->assignee_role }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tombol Aksi Langsung --}}
    <div class="flex items-center space-x-2 flex-shrink-0 self-end lg:self-center">
        @if(in_array($task->status, ['pending', 'in_progress', 'delegated', 'escalated']))
            {{-- Tombol Setujui Cepat --}}
            <form method="POST" action="{{ route('core.task-inbox.action', $task->id) }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="notes" value="Persetujuan cepat dari daftar tugas">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Setujui
                </button>
            </form>

            {{-- Tombol Tolak Cepat --}}
            <form method="POST" action="{{ route('core.task-inbox.action', $task->id) }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="notes" value="Penolakan cepat dari antrean">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-700 hover:bg-rose-600 text-slate-300 hover:text-white transition-colors border border-slate-600 focus:outline-none focus:ring-2 focus:ring-rose-500">
                    Tolak
                </button>
            </form>
        @endif

        {{-- Tombol Buka Detail --}}
        <a href="{{ route('core.task-inbox.show', $task->id) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors">
            <span>Rincian</span>
            <svg class="w-3.5 h-3.5 ml-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
</div>

