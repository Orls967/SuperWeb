@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🎵</span> Creator Streaming Royalties & Dispute Hold Escrow
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Idempotent micro-accruals per digital stream with automated 14-day hold clearance window.
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
                <div class="space-y-6">
                    <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                        <h3 class="text-lg font-semibold text-white mb-4">Accrue Stream Royalty</h3>
                        <form action="{{ route('venue.artist.royalties.accrue') }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Rights Contract</label>
                                <select name="rights_contract_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                    @foreach($contracts as $rc)
                                        <option value="{{ $rc->id }}">{{ $rc->contract_code }} ({{ $rc->royalty_share_percent }}%)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Batch Stream Count</label>
                                <input type="number" name="stream_count" value="15000" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Rate per Stream (IDR)</label>
                                <input type="number" name="rate_per_stream_idr" value="150" min="10" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                                Accrue into Held Escrow
                            </button>
                        </form>
                    </div>

                    <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                        <h3 class="text-lg font-semibold text-white mb-4">Release Held Royalties</h3>
                        <form action="{{ route('venue.artist.royalties.release') }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Select Contract to Settle</label>
                                <select name="rights_contract_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                    @foreach($contracts as $rc)
                                        <option value="{{ $rc->id }}">{{ $rc->contract_code }} (Held: Rp {{ number_format($rc->held_royalties_idr) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                                Release Royalties (Deduct 2.5% WHT)
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Streaming Rights Contracts</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Contract Code</th>
                                    <th class="p-3">Creator</th>
                                    <th class="p-3 text-right">Share %</th>
                                    <th class="p-3 text-right">Accrued</th>
                                    <th class="p-3 text-right">Held Escrow</th>
                                    <th class="p-3 text-right">Paid Out</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($contracts as $rc)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $rc->contract_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $rc->creator ? $rc->creator->stage_name : 'Creator #'.$rc->creator_id }}</td>
                                        <td class="p-3 text-right font-mono">{{ $rc->royalty_share_percent }}%</td>
                                        <td class="p-3 text-right font-mono text-gray-400">Rp {{ number_format($rc->accrued_royalties_idr) }}</td>
                                        <td class="p-3 text-right font-mono text-amber-400">Rp {{ number_format($rc->held_royalties_idr) }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($rc->paid_royalties_idr) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No streaming rights contracts found.</td>
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
