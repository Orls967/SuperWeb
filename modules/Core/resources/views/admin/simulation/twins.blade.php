<x-ds.shell title="Digital Twin Simulation Bus">
    <div class="space-y-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Simulasi Kernel', 'href' => route('admin.simulation.runs.index')],
                    ['label' => 'Digital Twins', 'href' => route('admin.simulation.twins.index')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Digital Twin State Bus</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan rantai hash bayangan entitas domain, snapshot deterministik, dan verifikasi integritas.</p>
            </div>
            <div class="flex items-center gap-3">
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.runs.index') }}">
                    Konsol Sesi
                </x-ds.button>
            </div>
        </div>

        <!-- Filter Bar -->
        <x-ds.card variant="default">
            <form method="GET" action="{{ route('admin.simulation.twins.index') }}" class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="entity_type" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Tipe Entitas</label>
                    <select id="entity_type" name="entity_type" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Tipe</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}" @selected(($filters['entity_type'] ?? '') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="entity_id" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ID Entitas</label>
                    <input type="text" id="entity_id" name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" placeholder="UUID atau ID..." class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                    <label for="sandbox" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Mode Sandbox</label>
                    <select id="sandbox" name="sandbox" class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua</option>
                        <option value="1" @selected(($filters['sandbox'] ?? '') === '1')>Hanya Sandbox</option>
                        <option value="0" @selected(($filters['sandbox'] ?? '') === '0')>Hanya Live</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-ds.button variant="primary" size="md" type="submit" class="w-full">
                        Filter
                    </x-ds.button>
                    <x-ds.button variant="secondary" size="md" href="{{ route('admin.simulation.twins.index') }}">
                        Reset
                    </x-ds.button>
                </div>
            </form>
        </x-ds.card>

        <!-- Tabel Daftar Twin State -->
        <x-ds.card variant="default">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3">ID Log</th>
                            <th class="px-4 py-3">Tipe Entitas</th>
                            <th class="px-4 py-3">ID Entitas</th>
                            <th class="px-4 py-3">Event Hash</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Waktu Rekam</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($twins as $item)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-xs">{{ $item->id }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $item->entity_type }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $item->entity_id }}</td>
                                <td class="px-4 py-3 font-mono text-xs truncate max-w-xs" title="{{ $item->event_hash }}">{{ Str::limit($item->event_hash, 16) }}</td>
                                <td class="px-4 py-3">
                                    @if ($item->is_sandbox)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            Sandbox
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                                            Live
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $item->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-ds.button variant="secondary" size="sm" href="{{ route('admin.simulation.twins.show', ['type' => $item->entity_type, 'id' => $item->entity_id]) }}">
                                        Audit Rantai
                                    </x-ds.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada data digital twin state ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($twins->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-700">
                    {{ $twins->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

