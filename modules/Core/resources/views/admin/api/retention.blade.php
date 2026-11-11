<x-ds.shell title="Pusat Retensi & Cold Archive">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Retensi & Arsip', 'href' => route('admin.api.retention')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Kebijakan Retensi Data & Cold Archive</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengarsipan data historis (> 5 tahun) dengan verifikasi hash chunk SHA-256 dan simulasi audit drill.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Eksekusi Pengarsipan Terverifikasi">
            @if ($runs->isEmpty())
                <x-ds.empty
                    title="Belum Ada Eksekusi Pengarsipan"
                    description="Belum ada siklus cold archive yang dijalankan. Gunakan api:archive-run untuk memulai."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">ID Run</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tabel Sumber</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tabel Arsip</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Baris Diarsipkan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Hash SHA-256</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($runs as $r)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">#{{ $r->id }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $r->source_table }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $r->archive_table }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ number_format($r->rows_archived) }}</td>
                                    <td class="px-4 py-2 font-mono text-xs truncate max-w-xs">{{ $r->chunk_sha256 }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$r->status === 'verified' ? 'success' : 'neutral'">
                                            {{ $r->status }}
                                        </x-ds.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

