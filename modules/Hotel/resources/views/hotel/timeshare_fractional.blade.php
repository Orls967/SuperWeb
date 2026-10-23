@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🏖️</span> Timeshare Fractional Villa Syndication
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fractional point tokenization, rental pool dividend yields, and investor capital management.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('hotel.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Hotel Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Mint Fractional Timeshare Unit</h3>
                    <form action="{{ route('hotel.pms.timeshare.mint') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Resort Property</label>
                            <select name="property_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Villa Unit Code</label>
                            <input type="text" name="unit_code" value="VILLA-SEAVIEW-101" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Unit Display Name</label>
                            <input type="text" name="unit_name" value="Cliffside Ocean View 3-Bedroom Villa" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Daily Rental Pool Rate (IDR)</label>
                            <input type="number" name="daily_rental_rate_idr" value="8500000" min="500000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Total Fractional Shares</label>
                            <input type="number" name="total_token_shares" value="100" min="10" max="1000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Mint Syndicated Villa Shares
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Syndicated Timeshare Units</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Unit Code</th>
                                    <th class="p-3">Villa Name</th>
                                    <th class="p-3 text-right">Shares</th>
                                    <th class="p-3 text-right">Rental Rate/Day</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($units as $u)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $u->unit_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $u->unit_name }}</td>
                                        <td class="p-3 text-right font-mono text-yellow-400">{{ $u->total_token_shares }} shares</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($u->daily_rental_rate_idr) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-gray-500">No timeshare units minted yet.</td>
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
