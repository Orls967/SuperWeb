<x-ds.shell title="Pusat Langganan Webhook">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'API v2', 'href' => route('admin.api.clients')],
                    ['label' => 'Langganan Webhook', 'href' => route('admin.api.webhooks')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Langganan & Pengiriman Webhook B2B</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tanda tangan HMAC-SHA256, circuit breaker otomatis (5 kegagalan berturut-turut), dan rotasi rahasia.</p>
            </div>
        </div>

        @include('core::api._states')

        <x-ds.card title="Daftar Langganan Webhook Aktif">
            @if ($webhooks->isEmpty())
                <x-ds.empty
                    title="Belum Ada Langganan Webhook"
                    description="Belum ada endpoint webhook yang didaftarkan oleh mitra eksternal."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Nama</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">URL Target</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Circuit State</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kegagalan Beruntun</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($webhooks as $wh)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $wh->code }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $wh->name }}</td>
                                    <td class="px-4 py-2 font-mono text-xs truncate max-w-xs">{{ $wh->target_url }}</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$wh->circuit_state === 'closed' ? 'success' : ($wh->circuit_state === 'open' ? 'danger' : 'neutral')">
                                            {{ $wh->circuit_state }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ $wh->consecutive_failures }} kali</td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="$wh->is_active ? 'success' : 'neutral'">
                                            {{ $wh->is_active ? 'Aktif' : 'Nonaktif' }}
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

