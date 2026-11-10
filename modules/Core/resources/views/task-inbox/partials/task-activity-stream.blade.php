{{-- modules/Core/resources/views/task-inbox/partials/task-activity-stream.blade.php --}}
@props([
    'activities' => [],
])

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6"
     role="region"
     aria-label="Aliran Aktivitas Riwayat Tugas">

    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h3 class="text-base font-bold text-gray-900 tracking-tight flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Jejak Audit & Aliran Aktivitas (Activity Stream)
            </h3>
            <p class="text-xs text-gray-500 mt-1">
                Catatan kronologis perubahan siklus hidup, pergantian penanggung jawab, dan pembaruan metadata tugas.
            </p>
        </div>

        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700">
            Immutable Audit Trail
        </span>
    </div>

    @if(empty($activities) || count($activities) === 0)
        <div class="p-6 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">
            Belum ada rekaman jejak audit tambahan untuk entitas tugas ini.
        </div>
    @else
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach($activities as $index => $act)
                    @php
                        $isLast = $loop->last;
                        $actionName = $act->action ?? 'update';
                        $iconColor = match($actionName) {
                            'created' => 'bg-emerald-500',
                            'approved' => 'bg-green-600',
                            'rejected' => 'bg-rose-500',
                            'delegated' => 'bg-amber-500',
                            'escalated' => 'bg-purple-600',
                            'commented' => 'bg-blue-500',
                            default => 'bg-gray-400'
                        };
                    @endphp
                    <li>
                        <div class="relative pb-8">
                            @if(!$isLast)
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full {{ $iconColor }} flex items-center justify-center ring-8 ring-white text-white shadow-sm">
                                        @if($actionName === 'approved')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        @elseif($actionName === 'rejected')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        @elseif($actionName === 'delegated')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        @elseif($actionName === 'escalated')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        @endif
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-xs text-gray-800">
                                            <span class="font-bold text-gray-900">{{ $act->performed_by_name ?? 'Sistem Otomatis' }}</span>
                                            {{ $act->description ?? 'Melakukan pembaruan pada status tugas.' }}
                                        </p>
                                        @if(!empty($act->metadata))
                                            <div class="mt-1 text-[11px] font-mono text-gray-500 bg-gray-50 p-2 rounded border border-gray-100">
                                                {{ is_array($act->metadata) ? json_encode($act->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $act->metadata }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-right text-[11px] whitespace-nowrap text-gray-400">
                                        <time datetime="{{ $act->created_at ?? now() }}">
                                            {{ !empty($act->created_at) ? \Carbon\Carbon::parse($act->created_at)->diffForHumans() : 'baru saja' }}
                                        </time>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

