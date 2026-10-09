<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ESG, GHG Emissions & Carbon Accounting') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">ESG Health & Audit Overview</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-emerald-50 p-4 rounded border border-emerald-200">
                        <span class="text-xs text-gray-500 uppercase">System Status</span>
                        <p class="text-xl font-bold text-emerald-700">{{ $audit['status'] }}</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded border border-blue-200">
                        <span class="text-xs text-gray-500 uppercase">Total CO2e Emitted</span>
                        <p class="text-xl font-bold text-blue-700">{{ $audit['total_co2e_tons'] }} tons</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded border border-green-200">
                        <span class="text-xs text-gray-500 uppercase">Offset Retired</span>
                        <p class="text-xl font-bold text-green-700">{{ $audit['total_offset_tons'] }} tons</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded border border-purple-200">
                        <span class="text-xs text-gray-500 uppercase">Active Credits</span>
                        <p class="text-xl font-bold text-purple-700">{{ $audit['credits_count'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">GHG Emissions Tracking (Scope 1, 2, 3)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Emission #</th>
                                <th class="px-4 py-2 text-left">Entity</th>
                                <th class="px-4 py-2 text-left">Scope</th>
                                <th class="px-4 py-2 text-left">Activity</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-right">CO2e (kg)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($emissions as $em)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $em->emission_number }}</td>
                                    <td class="px-4 py-2">{{ $em->entity_id }}</td>
                                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs bg-gray-100 uppercase">{{ $em->scope }}</span></td>
                                    <td class="px-4 py-2">{{ $em->activity_type }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($em->activity_data_amount, 2) }} {{ $em->activity_uom }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-gray-900">{{ number_format($em->co2e_kg, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-400">No emission records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
