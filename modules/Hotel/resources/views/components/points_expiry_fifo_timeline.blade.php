@props([
    'batches' => [],
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
     x-data="{ activeTab: 'ACTIVE' }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3 mb-5">
        <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Garis Waktu Penuaan Batch Poin (FIFO Timeline)</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Alokasi Poin Bertanggal & Pemantauan Kedaluwarsa 730 Hari (Algoritma H10)
            </p>
        </div>

        <div class="flex items-center space-x-1.5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
            <button type="button" @click="activeTab = 'ACTIVE'"
                    :class="activeTab === 'ACTIVE' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                    class="px-3 py-1.5 rounded-lg transition">
                Batch Aktif
            </button>
            <button type="button" @click="activeTab = 'EXPIRED'"
                    :class="activeTab === 'EXPIRED' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                    class="px-3 py-1.5 rounded-lg transition">
                Breakage Hangus
            </button>
        </div>
    </div>

    <!-- Timeline Container -->
    <div class="relative border-l-2 border-indigo-200 dark:border-indigo-900/60 ml-4 pl-6 space-y-6">
        @forelse($batches as $batch)
            @php
                $isExpired = $batch->expires_at < now();
                $daysRemaining = max(0, now()->diffInDays($batch->expires_at, false));
                $usagePct = $batch->initial_pts > 0 ? round((($batch->initial_pts - $batch->remaining_pts) / $batch->initial_pts) * 100) : 0;
            @endphp
            <div class="relative group"
                 x-show="(activeTab === 'ACTIVE' && '{{ $batch->state }}' !== 'BREAKAGE_WRITTEN_OFF') || (activeTab === 'EXPIRED' && '{{ $batch->state }}' === 'BREAKAGE_WRITTEN_OFF')"
                 x-transition>
                <!-- Dot indicator -->
                <div class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full border-2 border-white dark:border-slate-900
                    @if($batch->state === 'BREAKAGE_WRITTEN_OFF') bg-rose-500
                    @elseif($daysRemaining <= 30) bg-amber-500 animate-ping
                    @else bg-emerald-500 @endif"></div>

                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-md transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <div class="flex items-center space-x-2">
                            <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">{{ $batch->batch_number }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                @if($batch->originating_line === 'HTL_ROOM') bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300
                                @elseif($batch->originating_line === 'VEN_TICKET') bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300
                                @else bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 @endif">
                                {{ $batch->originating_line }}
                            </span>
                        </div>
                        <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Diperoleh: {{ $batch->earned_at->format('d M Y') }} · Kedaluwarsa: {{ $batch->expires_at->format('d M Y') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs pt-1">
                        <div>
                            <span class="text-slate-400 block text-[10px]">Poin Awal</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ number_format($batch->initial_pts) }} PTS</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Sisa Poin</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($batch->remaining_pts) }} PTS</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Liabilitas Moneter</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300">Rp {{ number_format($batch->remaining_pts * $batch->liability_per_pt_idr) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Masa Aktif</span>
                            <span class="font-bold @if($daysRemaining <= 30) text-rose-500 @else text-emerald-500 @endif">
                                {{ $daysRemaining }} Hari Tersisa
                            </span>
                        </div>
                    </div>

                    <!-- Usage progress -->
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-3">
                        <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $usagePct }}%"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-xs text-slate-400 py-6 text-center">
                Belum ada batch poin tercatat untuk anggota ini.
            </div>
        @endforelse
    </div>
</div>

