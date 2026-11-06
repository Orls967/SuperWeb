<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Papan Dispatch Pembangkit & Merit Order') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Optimasi Dispatch Ekonomi Per Jam & Status Unit Pembangkitan (Fase 126.1)
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Grid Frekuensi: 50.02 Hz
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Sistem Seimbang
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Form Manual Dispatch Trigger -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Jalankan Algoritma Dispatch Merit Order (E2)</h3>
                <form action="{{ route('egy.dispatch.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Target Jam Dispatch (WIB)</label>
                        <input type="datetime-local" name="hour_timestamp" value="{{ now()->startOfHour()->format('Y-m-d\TH:00') }}" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Total Beban Grid (MW)</label>
                        <input type="number" name="load_mw" value="950" min="1" step="1" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition">
                            Eksekusi Dispatch
                        </button>
                    </div>
                </form>
            </div>

            <!-- Fleet Status Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Armada Pembangkit Terdaftar (Genco Fleet)</h3>
                    <span class="text-xs text-gray-500">Total: {{ $assets->count() }} Pembangkit</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Kode & Nama Unit</th>
                                <th class="px-4 py-3 text-left">Teknologi / Bahan Bakar</th>
                                <th class="px-4 py-3 text-right">Kapasitas (MW)</th>
                                <th class="px-4 py-3 text-right">Beban Min (MW)</th>
                                <th class="px-4 py-3 text-right">Biaya Marjinal (IDR/kWh)</th>
                                <th class="px-4 py-3 text-center">Must-Run</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($assets as $asset)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <div class="font-bold">{{ $asset->asset_code }}</div>
                                        <div class="text-xs text-gray-500">{{ $asset->name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ in_array($asset->fuel_type, ['solar', 'hydro', 'geothermal', 'wind']) ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ strtoupper($asset->fuel_type ?? 'RENEWABLE') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($asset->capacity_mw ?? $asset->installed_capacity_mw ?? 0) }} MW</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-500">{{ number_format($asset->min_stable_mw ?? 0) }} MW</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700">
                                        Rp {{ number_format($asset->marginal_cost_idr ?? ($asset->marginal_cost_per_mwh_minor ? $asset->marginal_cost_per_mwh_minor / 1000 : 0)) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($asset->is_must_run)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-blue-100 text-blue-800">MUST-RUN</span>
                                        @else
                                            <span class="text-gray-400 text-2xs">FLEXIBLE</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ strtolower($asset->status) === 'online' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ strtoupper($asset->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 italic">
                                        Belum ada pembangkit yang terdaftar di armada genco.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Dispatch Runs & Orders -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Riwayat Eksekusi Dispatch & Cleared Orders</h3>
                <div class="space-y-4">
                    @forelse($runs as $run)
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50">
                            <div class="flex flex-wrap items-center justify-between border-b border-gray-200 pb-3 mb-3 gap-2">
                                <div>
                                    <span class="text-xs font-semibold uppercase text-indigo-600">Jam Dispatch</span>
                                    <span class="text-sm font-bold text-gray-900 ml-1">
                                        {{ \Carbon\Carbon::parse($run->hour_timestamp)->format('d M Y H:i') }}
                                    </span>
                                </div>
                                <div class="flex items-center space-x-4 text-xs">
                                    <div>Permintaan Beban: <strong class="font-mono text-gray-900">{{ number_format($run->load_mw) }} MW</strong></div>
                                    <div>Dispatched: <strong class="font-mono text-emerald-700">{{ number_format($run->total_dispatched_mw) }} MW</strong></div>
                                    <div>Curtailed: <strong class="font-mono text-amber-700">{{ number_format($run->total_curtailed_mw) }} MW</strong></div>
                                    <div>Total Biaya: <strong class="font-mono text-indigo-700">Rp {{ number_format($run->total_cost_thousand_idr * 1000) }}</strong></div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $run->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ strtoupper($run->status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-100 text-gray-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Unit Pembangkit</th>
                                            <th class="px-3 py-2 text-right">Diminta (MW)</th>
                                            <th class="px-3 py-2 text-right">Dispatched (MW)</th>
                                            <th class="px-3 py-2 text-right">Curtailed (MW)</th>
                                            <th class="px-3 py-2 text-right">Biaya Marjinal (IDR)</th>
                                            <th class="px-3 py-2 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach($run->orders as $order)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-800">
                                                    {{ $order->asset->name ?? $order->generation_asset_id }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-gray-500">
                                                    {{ number_format($order->requested_mw) }} MW
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono font-bold text-gray-900">
                                                    {{ number_format($order->dispatched_mw) }} MW
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-amber-600">
                                                    {{ number_format($order->curtailed_mw) }} MW
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-indigo-600">
                                                    Rp {{ number_format($order->marginal_cost_idr) }}
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-medium {{ $order->status === 'dispatched' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-50 text-gray-600' }}">
                                                        {{ strtoupper($order->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 italic py-4">Belum ada eksekusi dispatch pada sistem.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
