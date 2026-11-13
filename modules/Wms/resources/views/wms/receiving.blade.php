<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Inbound Receiving &amp; Tolerance Evaluation</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Riwayat Penerimaan GRN">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="py-2 pr-4">Nomor GRN</th>
                                <th class="py-2 pr-4">Produk</th>
                                <th class="py-2 pr-4">Expected</th>
                                <th class="py-2 pr-4">Received</th>
                                <th class="py-2">Keputusan Toleransi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($receipts as $receipt)
                                <tr>
                                    <td class="py-2 pr-4 font-mono">{{ $receipt->receipt_number }}</td>
                                    <td class="py-2 pr-4">#{{ $receipt->product_id }}</td>
                                    <td class="py-2 pr-4">{{ $receipt->expected_qty_base }}</td>
                                    <td class="py-2 pr-4">{{ $receipt->received_qty_base }}</td>
                                    <td class="py-2">{{ $receipt->tolerance_decision }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">Belum ada penerimaan GRN.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

