@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🍸</span> Dynamic Bottle Service Surge Pricing Engine
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Peak hour demand curves, VIP crowd density multipliers, and automated menu re-indexing.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.tables.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        VIP Tables
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {!! session('success') !!}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Calculate Bottle Surge</h3>
                    <form action="{{ route('venue.tables.surge.calculate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Premium Liquor SKU</label>
                            <input type="text" name="bottle_sku" value="DOM-PERIGNON-LUMINOUS-750ML" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Base Price (IDR)</label>
                            <input type="number" name="base_price_idr" value="8500000" min="500000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Venue Occupancy Load (%)</label>
                            <input type="number" step="0.5" name="venue_occupancy_pct" value="96.5" min="0" max="150" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Current Hour (0 - 23)</label>
                            <input type="number" name="hour_of_day" value="23" min="0" max="23" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-lg transition">
                            Apply Real-Time Surge Multiplier
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Dynamic Surge Multipliers</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">Peak Night (&ge; 95% Cap &amp; 22:00-03:00)</span>
                                <p class="text-xs text-gray-400">Extreme high demand ceiling</p>
                            </div>
                            <span class="px-2.5 py-1 bg-rose-900 text-rose-300 font-mono font-bold text-xs rounded border border-rose-700">1.50x (+50%)</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">Crowded Night (&ge; 80% Cap &amp; 22:00-03:00)</span>
                                <p class="text-xs text-gray-400">Surge late night tier</p>
                            </div>
                            <span class="px-2.5 py-1 bg-amber-900 text-amber-300 font-mono font-bold text-xs rounded border border-amber-700">1.30x (+30%)</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">Daytime Sunset Crowd (&ge; 80% Cap)</span>
                                <p class="text-xs text-gray-400">Moderate beach club surge</p>
                            </div>
                            <span class="px-2.5 py-1 bg-yellow-900 text-yellow-300 font-mono font-bold text-xs rounded border border-yellow-700">1.15x (+15%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
