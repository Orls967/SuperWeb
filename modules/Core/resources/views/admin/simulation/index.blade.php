<x-ds.shell title="Konsol Operator Simulasi">
    <div class="space-y-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Simulasi Kernel', 'href' => route('admin.simulation.runs.index')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Sesi Simulasi & Virtual Clock</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Orkestrasi waktu deterministik, Digital Twin Bus, dan kompresi siklus bisnis harian.</p>
            </div>
            <div class="flex items-center gap-3">
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.diff') }}">
                    Bandingkan (Diff)
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.twins.index') }}">
                    Digital Twins
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.export', ['type' => 'runs']) }}">
                    Ekspor CSV
                </x-ds.button>
            </div>
        </div>

        <!-- 4 KPI Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ds.stat label="Total Sesi" :value="$metrics['total_runs']" hint="Seluruh sesi tercatat" />
            <x-ds.stat label="Aktif Berjalan" :value="$metrics['running_runs']" hint="Status running" />
            <x-ds.stat label="Selesai (Completed)" :value="$metrics['completed_runs']" hint="Rantai fingerprint tuntas" />
            <x-ds.stat label="Mode Sandbox" :value="$metrics['sandbox_runs']" hint="Rollback transaksional" />
        </div>

        <!-- Filter Bar -->
        <x-ds.card variant="default">
            <form method="GET" action="{{ route('admin.simulation.runs.index') }}" class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="search" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Cari Sesi</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama sesi simulasi..." class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Status SM</label>
                    <select id="status" name="status" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Status</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                        <option value="provisioned" @selected(($filters['status'] ?? '') === 'provisioned')>Provisioned</option>
                        <option value="running" @selected(($filters['status'] ?? '') === 'running')>Running</option>
                        <option value="paused" @selected(($filters['status'] ?? '') === 'paused')>Paused</option>
                        <option value="checkpointed" @selected(($filters['status'] ?? '') === 'checkpointed')>Checkpointed</option>
                        <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                        <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                        <option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="mode" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Mode Eksekusi</label>
                    <select id="mode" name="mode" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Mode</option>
                        <option value="live" @selected(($filters['mode'] ?? '') === 'live')>Live</option>
                        <option value="sandbox" @selected(($filters['mode'] ?? '') === 'sandbox')>Sandbox (Rollback)</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-ds.button type="submit" variant="primary" size="md" class="w-full">
                        Filter
                    </x-ds.button>
                    @if(!empty(array_filter($filters)))
                        <x-ds.button variant="ghost" size="md" href="{{ route('admin.simulation.runs.index') }}">
                            Reset
                        </x-ds.button>
                    @endif
                </div>
            </form>
        </x-ds.card>

        <!-- Tabel Sesi Simulasi -->
        <x-ds.card variant="default">
            @if($runs->isEmpty())
                <x-ds.empty title="Belum Ada Sesi Simulasi" description="Jalankan perintah sim:run atau buat sesi baru dari antarmuka operator." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3.5">Nama Sesi</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5">Mode</th>
                                <th class="px-5 py-3.5 text-right font-mono">Hari Virtual</th>
                                <th class="px-5 py-3.5 text-right font-mono">Seed</th>
                                <th class="px-5 py-3.5 font-mono">Fingerprint Rantai</th>
                                <th class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($runs as $run)
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100">
                                        <a href="{{ route('admin.simulation.runs.show', $run->id) }}" class="text-blue-600 hover:underline">
                                            {{ $run->name }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-4">
                                        <x-ds.badge-status :status="$run->status" />
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $run->mode === 'sandbox' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300' }}">
                                            {{ strtoupper($run->mode) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-mono tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ $run->current_day }} / {{ $run->total_days }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-mono tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ $run->seed }}
                                    </td>
                                    <td class="px-5 py-4 font-mono text-xs text-slate-500 dark:text-slate-400 truncate max-w-xs" title="{{ $run->chain_hash }}">
                                        {{ $run->chain_hash ? substr($run->chain_hash, 0, 16) . '...' : '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <x-ds.button variant="ghost" size="sm" href="{{ route('admin.simulation.runs.show', $run->id) }}">
                                            Detail
                                        </x-ds.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($runs->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
                        {{ $runs->links() }}
                    </div>
                @endif
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

