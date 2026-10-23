@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>📊</span> AI Demand & Waste Forecasting (Holt-Winters)
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 75: Vending demand projection with automated MAPE tracking and perishable waste buffer.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('vending.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Vending Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Run Forecasting Engine</h3>
                    <form action="{{ route('vending.operations.demand-forecast.run') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Outlet / Unit Code</label>
                            <input type="text" name="outlet_code" value="OUTLET-JKT-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Item SKU</label>
                            <input type="text" name="item_code" value="BEV-COLD-BREW-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Forecast Date</label>
                            <input type="date" name="forecast_date" value="{{ date('Y-m-d') }}" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Footfall Traffic Multiplier</label>
                            <input type="number" step="0.1" name="footfall_factor" value="1.2" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Compute Holt-Winters Forecast
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Recent Demand Forecasts</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Outlet</th>
                                    <th class="p-3">Item Code</th>
                                    <th class="p-3">Forecast Date</th>
                                    <th class="p-3 text-right">Demand</th>
                                    <th class="p-3 text-right">Waste Est</th>
                                    <th class="p-3 text-right">MAPE</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($forecasts as $fc)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $fc->outlet_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $fc->item_code }}</td>
                                        <td class="p-3">{{ $fc->forecast_date }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">{{ number_format($fc->forecast_qty) }}</td>
                                        <td class="p-3 text-right font-mono text-rose-400">{{ number_format($fc->waste_forecast_qty) }}</td>
                                        <td class="p-3 text-right font-mono">{{ number_format($fc->mape, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No demand forecasts recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
