<x-ds.shell title="Metrik Penggunaan API v2">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Penggunaan & Kuota', 'href' => route('admin.api.usage')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Metrik & Pemantauan Penggunaan API v2</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Rollup per jam, pembatasan Token Bucket (HTTP 429), dan pemantauan latensi.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Ringkasan Rollup Penggunaan">
            @if ($rollups->isEmpty())
                <x-ds.empty
                    title="Belum Ada Data Rollup"
                    description="Data rollup per jam belum tersedia. Jalankan command api:usage-rollup untuk memproses."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Jam (Bucket)</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Klien</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Total Permintaan</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Berhasil (2xx)</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Dibatasi (429)</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Galat (5xx)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($rollups as $r)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $r->bucket_hour }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $r->client->client_code ?? $r->api_client_id }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ number_format($r->total_requests) }}</td>
                                    <td class="px-4 py-2 text-emerald-600 dark:text-emerald-400">{{ number_format($r->allowed_requests) }}</td>
                                    <td class="px-4 py-2 text-amber-600 dark:text-amber-400">{{ number_format($r->throttled_requests) }}</td>
                                    <td class="px-4 py-2 text-rose-600 dark:text-rose-400">{{ number_format($r->error_requests) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

