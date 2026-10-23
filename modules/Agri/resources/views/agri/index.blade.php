<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Agribusiness, Contract Farming & Cold Chain') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Agri" />


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

            <!-- 86.7 Dashboard: Peta NDVI & Status Cicilan Modal -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Satellite NDVI Canopy Scans & Tranche Ratchet</h3>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded">Sentinel-2 Multispectral</span>
                </div>
                <div class="overflow-x-auto mb-6">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Scan Code</th>
                                <th class="px-4 py-2 text-left">Plot Code</th>
                                <th class="px-4 py-2 text-left">Scan Date</th>
                                <th class="px-4 py-2 text-right">NDVI Score</th>
                                <th class="px-4 py-2 text-left">Crop Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($satelliteScans as $scan)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $scan->scan_code }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $scan->plot_code }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $scan->scan_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-emerald-700">{{ number_format((float)$scan->ndvi_score, 3) }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $scan->crop_health_status === 'HEALTHY' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $scan->crop_health_status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-400">No satellite scans recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h4 class="text-md font-bold text-gray-800 mb-3">Financing Tranches & Ratchet Evaluation</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Tranche #</th>
                                <th class="px-4 py-2 text-left">Plot Code</th>
                                <th class="px-4 py-2 text-right">Amount (IDR)</th>
                                <th class="px-4 py-2 text-right">Min NDVI Required</th>
                                <th class="px-4 py-2 text-right">Evaluated NDVI</th>
                                <th class="px-4 py-2 text-left">Disbursement Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($financingInstallments as $inst)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $inst->installment_code }} (Tranche {{ $inst->tranche_number }})</td>
                                    <td class="px-4 py-2">{{ $inst->plot_code }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($inst->amount_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format((float)$inst->required_min_ndvi, 3) }}</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold">{{ $inst->evaluated_ndvi ? number_format((float)$inst->evaluated_ndvi, 3) : '-' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $inst->status === 'DISBURSED' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $inst->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-400">No financing installments pending evaluation.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 86.7 Dashboard: DAO Corporate Governance & Hash-Chained Votes -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">DAO Corporate Governance & Verifiable Voting</h3>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-indigo-100 text-indigo-800 rounded">Hash-Chained Consensus</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @forelse($daoProposals as $prop)
                        <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-mono text-xs text-gray-500">{{ $prop->proposal_code }}</span>
                                    <h4 class="font-bold text-gray-900">{{ $prop->title }}</h4>
                                </div>
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $prop->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : ($prop->status === 'ACTIVE' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ $prop->status }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600">{{ $prop->description }}</p>
                            <div class="bg-gray-50 p-2.5 rounded text-xs space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Quorum Required:</span>
                                    <span class="font-semibold">{{ number_format($prop->quorum_weight_required) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Votes Cast:</span>
                                    <span class="font-semibold text-emerald-600">{{ number_format($prop->total_yes_weight) }} YES / {{ number_format($prop->total_no_weight) }} NO</span>
                                </div>
                                @if($prop->execution_reference_code)
                                    <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                                        <span class="text-gray-500">Auto Project Draft:</span>
                                        <span class="font-mono text-indigo-600 font-bold">{{ $prop->execution_reference_code }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-span-2 text-center py-6 text-gray-400">No corporate DAO proposals currently listed.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
