<x-ds.shell title="Kelola Klien API v2">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Klien API', 'href' => route('admin.api.clients')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Manajemen Klien API v2</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengelolaan token API, rotasi kunci (maksimal 2 kunci aktif), dan kuota rate limit (Token Bucket).</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Daftar Klien API Terdaftar">
            @if ($clients->isEmpty())
                <x-ds.empty
                    title="Belum Ada Klien API"
                    description="Belum ada klien B2B atau aplikasi pihak ketiga yang didaftarkan pada API v2."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode Klien</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Nama</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tenant</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Rate Limit</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kunci Aktif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($clients as $c)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-medium">{{ $c->client_code }}</td>
                                    <td class="px-4 py-2">{{ $c->name }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $c->tenant_id }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $c->rate_limit_per_minute }}/m (burst: {{ $c->burst_limit }})</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$c->is_active ? 'success' : 'neutral'">
                                            {{ $c->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ $c->keys->where('status', 'active')->count() }} kunci</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

