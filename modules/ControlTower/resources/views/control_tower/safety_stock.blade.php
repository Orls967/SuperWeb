<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Tower: Kebijakan Safety Stock ABC/XYZ</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Matriks ABC/XYZ &amp; Dynamic Safety Stock">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="py-2 pr-4">SKU</th>
                                <th class="py-2 pr-4">Kelas</th>
                                <th class="py-2 pr-4">Service Level (bps)</th>
                                <th class="py-2 pr-4">Safety Stock</th>
                                <th class="py-2">Reorder Point</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($policies as $pol)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $pol->item_code }}</td>
                                    <td class="py-2 pr-4">{{ $pol->abc_class }}{{ $pol->xyz_class }}</td>
                                    <td class="py-2 pr-4">{{ $pol->service_level_bps }}</td>
                                    <td class="py-2 pr-4">{{ $pol->safety_stock_qty }}</td>
                                    <td class="py-2">{{ $pol->reorder_point_qty }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-500">Belum ada kebijakan safety stock.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

