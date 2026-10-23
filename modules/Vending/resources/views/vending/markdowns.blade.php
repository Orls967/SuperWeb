@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🏷️</span> Dynamic Near-Expiry Markdown Engine
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Dynamic discount tiering to maximize salvage recovery before perishable expiration.
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
                    {!! session('success') !!}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Simulate Dynamic Markdown</h3>
                    <form action="{{ route('vending.operations.markdowns.calculate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Perishable SKU</label>
                            <input type="text" name="item_code" value="FRESH-SANDWICH-TUNA" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Original Price (IDR)</label>
                            <input type="number" name="original_price_idr" value="35000" min="1000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Hours Remaining Until Expiration</label>
                            <input type="number" name="hours_until_expiry" value="10" min="0" max="72" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-lg transition">
                            Evaluate Markdown Tier
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Algorithmic Markdown Schedule</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&le; 6 Hours to Expiry</span>
                                <p class="text-xs text-gray-400">Critical clearance tier</p>
                            </div>
                            <span class="px-2.5 py-1 bg-rose-900 text-rose-300 font-mono font-bold text-xs rounded border border-rose-700">70% OFF</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&le; 12 Hours to Expiry</span>
                                <p class="text-xs text-gray-400">Accelerated markdown</p>
                            </div>
                            <span class="px-2.5 py-1 bg-amber-900 text-amber-300 font-mono font-bold text-xs rounded border border-amber-700">50% OFF</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&le; 24 Hours to Expiry</span>
                                <p class="text-xs text-gray-400">Early clearance</p>
                            </div>
                            <span class="px-2.5 py-1 bg-yellow-900 text-yellow-300 font-mono font-bold text-xs rounded border border-yellow-700">25% OFF</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&gt; 24 Hours to Expiry</span>
                                <p class="text-xs text-gray-400">Standard retail pricing</p>
                            </div>
                            <span class="px-2.5 py-1 bg-emerald-900 text-emerald-300 font-mono font-bold text-xs rounded border border-emerald-700">Full Price</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
