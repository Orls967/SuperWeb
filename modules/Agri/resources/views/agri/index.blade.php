<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Agribusiness, Contract Farming & Cold Chain') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Contract Farming & Collection Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-lime-50 p-4 rounded border border-lime-200">
                        <span class="text-xs text-gray-500 uppercase">System Status</span>
                        <p class="text-xl font-bold text-lime-700">{{ $audit['status'] }}</p>
                    </div>
                    <div class="bg-emerald-50 p-4 rounded border border-emerald-200">
                        <span class="text-xs text-gray-500 uppercase">Partner Farmers</span>
                        <p class="text-xl font-bold text-emerald-700">{{ $audit['farmers_count'] }}</p>
                    </div>
                    <div class="bg-amber-50 p-4 rounded border border-amber-200">
                        <span class="text-xs text-gray-500 uppercase">Harvest Collected</span>
                        <p class="text-xl font-bold text-amber-700">{{ number_format($audit['total_harvest_kg'], 1) }} kg</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded border border-blue-200">
                        <span class="text-xs text-gray-500 uppercase">Net Farmer Payout</span>
                        <p class="text-xl font-bold text-blue-700">Rp {{ number_format($audit['total_net_payout_idr'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Collection Center Batches & Grading</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Batch #</th>
                                <th class="px-4 py-2 text-left">Farmer</th>
                                <th class="px-4 py-2 text-left">Commodity</th>
                                <th class="px-4 py-2 text-left">Grade</th>
                                <th class="px-4 py-2 text-right">Gross Wt (kg)</th>
                                <th class="px-4 py-2 text-right">Net Payout</th>
                                <th class="px-4 py-2 text-left">Destination</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($batches as $b)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $b->batch_number }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $b->contract->farmer->full_name ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $b->contract->commodity ?? '-' }}</td>
                                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs bg-lime-100 text-lime-800 uppercase">{{ $b->grade }}</span></td>
                                    <td class="px-4 py-2 text-right">{{ number_format($b->gross_weight_kg, 2) }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-emerald-600">Rp {{ number_format($b->net_payout_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $b->destination_unit }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-4 text-center text-gray-400">No collection center batches recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
