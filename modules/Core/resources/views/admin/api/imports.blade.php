<x-ds.shell title="Pusat Impor & Ekspor Massal">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Impor & Ekspor', 'href' => route('admin.api.imports')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Impor & Ekspor Massal (Bulk I/O)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Streaming SplFileObject, eksekusi bertahap per 500 baris, checkpoint resume, dan ekspor ZIP.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Riwayat Pekerjaan Impor Massal">
            @if ($importJobs->isEmpty())
                <x-ds.empty
                    title="Belum Ada Pekerjaan Impor"
                    description="Pekerjaan impor katalog B2B, harga HET, atau kode HS belum pernah dijalankan."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">ID</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Target</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Berkas</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Total Baris</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Progres</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($importJobs as $job)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">#{{ $job->id }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $job->target_key }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $job->source_filename }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$job->status === 'completed' ? 'success' : ($job->status === 'failed' ? 'danger' : 'neutral')">
                                            {{ $job->status }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ number_format($job->total_rows) }} (valid: {{ number_format($job->valid_rows) }}, galat: {{ number_format($job->error_rows) }})</td>
                                    <td class="px-4 py-2 text-xs font-semibold">{{ number_format($job->processed_rows) }} baris</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

