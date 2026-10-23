@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🔥</span> Smelter Metallurgical Recovery &amp; Mass Balance
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        RKEF &amp; HPAL processing: Metal recovery rate efficiency, energy intensity (kWh/ton), and byproduct slag monetization.
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
                    <h3 class="text-lg font-semibold text-white mb-4">Record Smelter Campaign</h3>
                    <form action="{{ route('mining.operations.smelter.run') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Smelter Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Smelter Campaign Code</label>
                            <input type="text" name="run_code" value="RUN-RKEF-2026-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Output Commodity</label>
                            <select name="output_commodity" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="NPI">Nickel Pig Iron (NPI 12%)</option>
                                <option value="NICKEL_MATTE">High-Grade Nickel Matte (75%)</option>
                                <option value="MHP">Mixed Hydroxide Precipitate (MHP)</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Input Ore (Tons)</label>
                                <input type="number" step="0.1" name="input_ore_tonnage" value="1000.0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Input Grade (% Ni)</label>
                                <input type="number" step="0.01" name="input_grade_pct" value="1.80" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Output Metal (Tons)</label>
                                <input type="number" step="0.1" name="output_metal_tonnage" value="16.2" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Slag Byproduct (Tons)</label>
                                <input type="number" step="0.1" name="byproduct_slag_tonnage" value="850.0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Slag Sales Revenue (IDR)</label>
                            <input type="number" name="byproduct_revenue_idr" value="125000000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Process Smelter Metallurgy Run
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Completed Smelting Campaigns</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Run Code</th>
                                    <th class="p-3">Product</th>
                                    <th class="p-3 text-right">Input Ore</th>
                                    <th class="p-3 text-right">Output Metal</th>
                                    <th class="p-3 text-right">Recovery Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($runs as $r)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $r->run_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $r->output_commodity }}</td>
                                        <td class="p-3 text-right font-mono">{{ $r->input_ore_tonnage }} Ton</td>
                                        <td class="p-3 text-right font-mono text-white">{{ $r->output_metal_tonnage }} Ton</td>
                                        <td class="p-3 text-right font-mono text-emerald-400 font-bold">{{ $r->recovery_rate_pct }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No smelter runs recorded yet.</td>
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
