@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🎧</span> Headliner Artist Escrow & Withholding Tax (PPh 23) Settlement
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Door share revenue calculation, advance deduction, and automated Indonesian tax escrow.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Venue Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Settle Artist Door Share</h3>
                    <form action="{{ route('venue.artist.settle') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Artist Contract</label>
                            <select name="artist_contract_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($contracts as $c)
                                    <option value="{{ $c->id }}">{{ $c->artist_name }} ({{ $c->contract_number }}) &ndash; {{ $c->door_share_percentage }}%</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Total Door Revenue (IDR)</label>
                            <input type="number" name="total_door_sales_idr" value="150000000" min="1000000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                            Execute Payout & Tax Withholding
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Artist Performance Contracts</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Contract #</th>
                                    <th class="p-3">Artist</th>
                                    <th class="p-3 text-right">Share %</th>
                                    <th class="p-3 text-right">Advance Paid</th>
                                    <th class="p-3 text-right">Net Payout</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($contracts as $c)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $c->contract_number }}</td>
                                        <td class="p-3 font-bold text-white">{{ $c->artist_name }}</td>
                                        <td class="p-3 text-right font-mono">{{ $c->door_share_percentage }}%</td>
                                        <td class="p-3 text-right font-mono text-yellow-400">Rp {{ number_format($c->advance_amount) }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($c->net_payout_amount) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">
                                                {{ $c->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No artist contracts found.</td>
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
