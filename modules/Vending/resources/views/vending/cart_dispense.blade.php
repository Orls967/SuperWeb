@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🛒</span> Unmanned Smart Cart Dispense
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Autonomous hardware telemetry, coil motor pulse, and QRIS instant checkout settlement.
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
                    <h3 class="text-lg font-semibold text-white mb-4">Simulate Cart Dispense</h3>
                    <form action="{{ route('vending.operations.cart-dispense.dispense') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Vending Kiosk</label>
                            <select name="vending_unit_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->location_name }} ({{ $unit->device_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Product SKU</label>
                            <input type="text" name="item_code" value="MINERAL-WATER-500ML" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Quantity</label>
                            <input type="number" name="qty" value="1" min="1" max="10" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Price (IDR)</label>
                            <input type="number" name="unit_price_idr" value="8000" min="500" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Trigger Motor Pulse & Dispense
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Recent Dispense Transactions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Tx Ref</th>
                                    <th class="p-3">Kiosk</th>
                                    <th class="p-3">SKU</th>
                                    <th class="p-3 text-right">Qty</th>
                                    <th class="p-3 text-right">Total (IDR)</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($transactions as $tx)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $tx->tx_code }}</td>
                                        <td class="p-3">{{ $tx->unit ? $tx->unit->location_name : 'Kiosk #'.$tx->vending_unit_id }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $tx->item_code }}</td>
                                        <td class="p-3 text-right font-mono">{{ $tx->qty }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($tx->total_amount_idr) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">
                                                {{ $tx->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No dispense transactions on record.</td>
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
