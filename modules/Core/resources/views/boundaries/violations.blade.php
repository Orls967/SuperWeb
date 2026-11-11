<x-ds.shell title="Daftar Pelanggaran Batas Modul">
    <div class="space-y-6">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Batas Arsitektur', 'href' => route('admin.boundaries.map')],
                    ['label' => 'Pelanggaran', 'href' => route('admin.boundaries.violations')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Daftar Pelanggaran Batas Arsitektur</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan pelanggaran aturan A1, A2, A3 dengan pengawasan State Machine 7 status.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.map') }}">
                    Peta Modul
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.waivers') }}">
                    Kelola Waiver
                </x-ds.button>
                <x-ds.button variant="secondary" size="md" href="{{ route('admin.boundaries.export.violations') }}">
                    Ekspor CSV
                </x-ds.button>
            </div>
        </div>

        <!-- Filter Bar -->
        <x-ds.card title="Penyaring Data">
            <form method="GET" action="{{ route('admin.boundaries.violations') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Status SM</label>
                    <select name="status" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" {{ $currentFilter['status'] === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Aturan (Rule ID)</label>
                    <select name="rule_id" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                        <option value="">Semua Aturan</option>
                        <option value="A1" {{ $currentFilter['rule_id'] === 'A1' ? 'selected' : '' }}>A1 (Cross-Module Domain)</option>
                        <option value="A2" {{ $currentFilter['rule_id'] === 'A2' ? 'selected' : '' }}>A2 (Foreign Table Prefix)</option>
                        <option value="A3" {{ $currentFilter['rule_id'] === 'A3' ? 'selected' : '' }}>A3 (Circular Dependency)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Kategori</label>
                    <select name="category" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" {{ $currentFilter['category'] === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <x-ds.button type="submit" variant="primary" size="md" class="w-full">
                        Terapkan Filter
                    </x-ds.button>
                </div>
            </form>
        </x-ds.card>

        <!-- Violations Table -->
        <x-ds.card title="Daftar Rekaman Pelanggaran">
            @if ($violations->isEmpty())
                <x-ds.empty title="Tidak Ada Pelanggaran" description="Tidak ditemukan rekaman pelanggaran batas yang cocok dengan filter aktif." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3">ID / Aturan</th>
                                <th class="px-4 py-3">Modul (Klien &rarr; Pemilik)</th>
                                <th class="px-4 py-3">Lokasi Berkas & Tanda Tangan</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($violations as $v)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-slate-100">
                                        #{{ $v->id }}
                                        <span class="ml-1 px-2 py-0.5 text-xs bg-slate-200 dark:bg-slate-700 rounded">{{ $v->rule_id }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                        <span class="font-semibold">{{ $v->client_module }}</span>
                                        <span class="text-slate-400 mx-1">&rarr;</span>
                                        <span class="font-semibold">{{ $v->owner_module }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs max-w-xs truncate">
                                        <div>{{ $v->file_path }}:{{ $v->line_number }}</div>
                                        <div class="text-slate-500 truncate">{{ $v->signature }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ds.badge-status :status="$v->status?->value ?? (string) $v->status" />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-ds.button variant="ghost" size="sm" href="{{ route('admin.boundaries.violations.show', $v->id) }}">
                                            Detail
                                        </x-ds.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $violations->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

