<x-ds.shell title="Detail Digital Twin & Hash Chain">
    <div class="space-y-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Simulasi Kernel', 'href' => route('admin.simulation.runs.index')],
                    ['label' => 'Digital Twins', 'href' => route('admin.simulation.twins.index')],
                    ['label' => $entityType . ' #' . $entityId, 'href' => '#'],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ $entityType }} #{{ $entityId }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Verifikasi rantai hash sha256, snapshot periodik, dan riwayat evolusi state.</p>
            </div>
            <div>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.twins.index') }}">
                    Kembali ke Daftar Twin
                </x-ds.button>
            </div>
        </div>

        <!-- Kartu Status Integritas Rantai -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ds.card variant="default" class="p-4">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Integritas Hash Chain</span>
                <div class="mt-2 flex items-center gap-2">
                    @if ($isChainValid)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            VALID & TIDAK KORUP
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                            TERKONTAMINASI / ANOMALI
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-2">Diverifikasi via DigitalTwinService::verifyChain()</p>
            </x-ds.card>

            <x-ds.card variant="default" class="p-4">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Riwayat Event</span>
                <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ $history->count() }} Transisi
                </div>
                <p class="text-xs text-slate-500 mt-1">Append-only state entries</p>
            </x-ds.card>

            <x-ds.card variant="default" class="p-4">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Snapshot Tersimpan</span>
                <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ $snapshots->count() }} Snapshot
                </div>
                <p class="text-xs text-slate-500 mt-1">Snapshot point harian / mingguan</p>
            </x-ds.card>
        </div>

        <!-- Tabel Snapshot -->
        @if ($snapshots->isNotEmpty())
            <x-ds.card variant="default">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Daftar Snapshot Entitas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-4 py-3">Hari ke-</th>
                                <th class="px-4 py-3">Run ID</th>
                                <th class="px-4 py-3">State Hash</th>
                                <th class="px-4 py-3">Payload Snapshot</th>
                                <th class="px-4 py-3">Waktu Rekam</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($snapshots as $snap)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ $snap->day_number }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $snap->sim_run_id ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $snap->state_hash }}</td>
                                    <td class="px-4 py-3 font-mono text-xs max-w-md truncate">{{ json_encode($snap->state_payload) }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $snap->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        @endif

        <!-- Riwayat Transisi Event Hash Chain -->
        <x-ds.card variant="default">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Log Rantai Hash Event (Append-Only)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3">#ID</th>
                            <th class="px-4 py-3">Event Hash</th>
                            <th class="px-4 py-3">Prev Hash</th>
                            <th class="px-4 py-3">State Data</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($history as $h)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $h->id }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $h->event_hash }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $h->prev_hash ?? 'GENESIS' }}</td>
                                <td class="px-4 py-3 font-mono text-xs max-w-sm truncate">{{ json_encode($h->state_payload) }}</td>
                                <td class="px-4 py-3">
                                    @if ($h->is_sandbox)
                                        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Sandbox</span>
                                    @else
                                        <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">Live</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $h->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Belum ada log transisi event untuk entitas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

