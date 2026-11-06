<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Smart Grid & Power Generation Command Center
                </h2>
                <p class="text-sm text-gray-500">Smart Grid Operations & Transformer Load: pemantauan transmisi, kapasitas pembangkit, dan beban trafo</p>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" data-export-csv="grid-dispatch-csv" data-table-search="grid-table" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
                <button type="button" data-open-simulator="egy-grid-simulator" class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Simulator Grid
                </button>
                <a href="{{ route('egy.dispatch.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm">
                    Papan Dispatch & Merit Order
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Kapasitas Pembangkit</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($totalOnlineCapacityMw) }}</span>
                        <span class="text-xs font-semibold text-indigo-600">MW Online</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Beban Jaringan Realtime</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($totalCurrentLoadMw) }}</span>
                        <span class="text-xs font-semibold text-amber-600">MW</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Prakiraan Beban Puncak</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($forecastLoadMw) }}</span>
                        <span class="text-xs font-semibold text-rose-600">MW (Forecast)</span>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Operating Reserve Margin</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($reserveMarginMw) }}</span>
                        <span class="text-xs font-semibold text-emerald-600">MW Tersedia</span>
                    </div>
                </div>
            </div>

            <!-- Generation Units Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-gray-800">Unit Pembangkit Terdaftar (Genco Assets)</h3>
                        <p class="text-xs text-gray-500">Urutan biaya marjinal unit pembangkit untuk unit commitment</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs uppercase font-medium text-gray-400">
                            <tr>
                                <th class="px-5 py-3">Kode Unit</th>
                                <th class="px-5 py-3">Nama Pembangkit</th>
                                <th class="px-5 py-3">Bahan Bakar</th>
                                <th class="px-5 py-3 text-right">Kapasitas (MW)</th>
                                <th class="px-5 py-3 text-right">Min-Stable (MW)</th>
                                <th class="px-5 py-3 text-right">Biaya Marjinal (Rp/MWh)</th>
                                <th class="px-5 py-3">Must Run</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($generationAssets as $asset)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $asset->asset_code }}</td>
                                    <td class="px-5 py-3.5">{{ $asset->name }}</td>
                                    <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded text-xs uppercase bg-gray-100 font-medium">{{ $asset->fuel_type ?? $asset->asset_type }}</span></td>
                                    <td class="px-5 py-3.5 text-right font-semibold">{{ number_format($asset->capacity_mw > 0 ? $asset->capacity_mw : $asset->installed_capacity_mw) }}</td>
                                    <td class="px-5 py-3.5 text-right">{{ number_format($asset->min_stable_mw) }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ number_format($asset->marginal_cost_idr > 0 ? $asset->marginal_cost_idr : $asset->marginal_cost_per_mwh_minor) }}</td>
                                    <td class="px-5 py-3.5">
                                        @if($asset->is_must_run)
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full font-semibold">YA</span>
                                        @else
                                            <span class="text-xs text-gray-400">TIDAK</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ strtolower($asset->status) === 'online' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ strtoupper($asset->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-6 text-center text-gray-400">Belum ada pembangkit terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Substation Nodes Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Topologi Gardu Induk & Jaringan Transmisi</h3>
                    <p class="text-xs text-gray-500">Monitoring beban gardu trafo terhadap kapasitas desain</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs uppercase font-medium text-gray-400">
                            <tr>
                                <th class="px-5 py-3">Kode Gardu</th>
                                <th class="px-5 py-3">Wilayah</th>
                                <th class="px-5 py-3 text-right">Tegangan (kV)</th>
                                <th class="px-5 py-3 text-right">Beban Saat Ini (MW)</th>
                                <th class="px-5 py-3 text-right">Kapasitas Puncak (MW)</th>
                                <th class="px-5 py-3">Utilisasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($nodes as $node)
                                @php
                                    $utilPct = $node->peak_capacity_mw > 0 ? round(($node->current_load_mw / $node->peak_capacity_mw) * 100, 1) : 0;
                                @endphp
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $node->node_code }}</td>
                                    <td class="px-5 py-3.5">{{ $node->name }} ({{ $node->substation_region }})</td>
                                    <td class="px-5 py-3.5 text-right font-mono">{{ number_format($node->nominal_voltage_kv, 1) }}</td>
                                    <td class="px-5 py-3.5 text-right font-semibold">{{ number_format($node->current_load_mw, 1) }}</td>
                                    <td class="px-5 py-3.5 text-right">{{ number_format($node->peak_capacity_mw, 1) }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-24 bg-gray-200 rounded-full h-2 overflow-hidden">
                                                <div class="h-2 rounded-full {{ $utilPct > 90 ? 'bg-rose-500' : ($utilPct > 75 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, $utilPct) }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium text-gray-600">{{ $utilPct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-6 text-center text-gray-400">Belum ada data gardu jaringan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
