<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Lot Quarantine &amp; QA Hold</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Stok dalam Karantina / Blocked">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="py-2 pr-4">Bin</th>
                                <th class="py-2 pr-4">Produk</th>
                                <th class="py-2 pr-4">Lot</th>
                                <th class="py-2 pr-4">Qty</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($quarantined_stocks as $item)
                                <tr>
                                    <td class="py-2 pr-4">#{{ $item->bin_id }}</td>
                                    <td class="py-2 pr-4">#{{ $item->product_id }}</td>
                                    <td class="py-2 pr-4 font-mono">{{ $item->lot_number ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $item->qty_base }}</td>
                                    <td class="py-2 pr-4">{{ $item->stock_status }}</td>
                                    <td class="py-2">{{ $item->quarantine_reason ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-gray-500">Tidak ada stok dalam karantina.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
