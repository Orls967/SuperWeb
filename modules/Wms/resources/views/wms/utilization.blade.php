<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Space Utilization &amp; Dead Stock Analytics</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Snapshot Utilisasi Gudang">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="py-2 pr-4">Tanggal</th>
                                <th class="py-2 pr-4">Gudang</th>
                                <th class="py-2 pr-4">Terisi / Total Bin</th>
                                <th class="py-2 pr-4">Utilisasi (bps)</th>
                                <th class="py-2">Dead Stock SKU</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($snapshots as $snap)
                                <tr>
                                    <td class="py-2 pr-4">{{ $snap->snapshot_date?->format('Y-m-d') }}</td>
                                    <td class="py-2 pr-4">#{{ $snap->warehouse_id }}</td>
                                    <td class="py-2 pr-4">{{ $snap->occupied_bins }} / {{ $snap->total_bins }}</td>
                                    <td class="py-2 pr-4">{{ $snap->utilization_bps }}</td>
                                    <td class="py-2">{{ $snap->dead_stock_sku_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">Belum ada snapshot utilisasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

