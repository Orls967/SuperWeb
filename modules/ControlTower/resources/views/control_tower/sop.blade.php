<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Tower: Siklus S&amp;OP Kolaboratif</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Daftar Siklus S&amp;OP">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="py-2 pr-4">Kode</th>
                                <th class="py-2 pr-4">Periode</th>
                                <th class="py-2 pr-4">Skenario</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Demand</th>
                                <th class="py-2">Supply</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($cycles as $cycle)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $cycle->cycle_code }}</td>
                                    <td class="py-2 pr-4">{{ $cycle->period }}</td>
                                    <td class="py-2 pr-4">{{ $cycle->scenario_type }}</td>
                                    <td class="py-2 pr-4">
                                        <x-ds.badge :variant="$cycle->status === 'published' ? 'success' : 'info'">
                                            {{ $cycle->status }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="py-2 pr-4">{{ $cycle->demand_plan_qty }}</td>
                                    <td class="py-2">{{ $cycle->supply_plan_qty }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-gray-500">Belum ada siklus S&amp;OP tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

