<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Tower: Executive Supply Chain KPI</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Snapshot KPI Rantai Pasok">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="py-2 pr-4">Tanggal</th>
                                <th class="py-2 pr-4">OTIF (bps)</th>
                                <th class="py-2 pr-4">Cash-to-Cash (Hari)</th>
                                <th class="py-2 pr-4">Inventory DOS (Hari)</th>
                                <th class="py-2">Lead Time (Jam)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($snapshots as $snap)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $snap->snapshot_date?->toDateString() }}</td>
                                    <td class="py-2 pr-4">{{ $snap->otif_bps }}</td>
                                    <td class="py-2 pr-4">{{ $snap->cash_to_cash_days }}</td>
                                    <td class="py-2 pr-4">{{ $snap->inventory_dos_days }}</td>
                                    <td class="py-2">{{ $snap->fulfillment_lead_time_hours }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-500">Belum ada snapshot KPI.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

