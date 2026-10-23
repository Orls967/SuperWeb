@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>⚖️</span> Weighbridge Custody Tickets &amp; Hash-Chain
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Gross-Tare-Net measurement, tamper-evident SHA-256 block linking, and multi-element assay recording.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('mining.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Mining Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Record Weighbridge Ticket</h3>
                    <form action="{{ route('mining.operations.weighbridge.ticket') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Concession Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Truck License Plate</label>
                            <input type="text" name="truck_plate_number" value="DT-8821-XX" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Gross (Ton)</label>
                                <input type="number" step="0.01" name="gross_weight_ton" value="48.50" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Tare (Ton)</label>
                                <input type="number" step="0.01" name="tare_weight_ton" value="12.20" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Assayed Grade (% Ni)</label>
                            <input type="number" step="0.01" name="nickel_grade_percentage" value="1.82" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Destination Stockpile</label>
                            <input type="text" name="destination_stockpile" value="STOCKPILE-ROM-02" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Seal Weighbridge Block Ticket
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Cryptographic Weighbridge Chain</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Ticket #</th>
                                    <th class="p-3">Truck</th>
                                    <th class="p-3 text-right">Net Weight</th>
                                    <th class="p-3 text-right">Grade Ni</th>
                                    <th class="p-3">SHA-256 Hash</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($tickets as $t)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $t->ticket_number }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $t->truck_plate_number }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">{{ $t->net_weight_ton }} Ton</td>
                                        <td class="p-3 text-right font-mono">{{ $t->nickel_grade_percentage }}%</td>
                                        <td class="p-3 font-mono text-[11px] text-gray-400">{{ substr($t->hash, 0, 16) }}...</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No weighbridge tickets recorded.</td>
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
