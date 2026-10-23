@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>⛏️</span> Mine Block Extraction &amp; Stripping Ratio (SR)
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 93: Open-pit extraction scheduling, waste overburden stripping economics, and cutoff ratio validation.
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
                    <h3 class="text-lg font-semibold text-white mb-4">Plan Extraction Block</h3>
                    <form action="{{ route('mining.operations.block-planning.plan') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Concession Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->commodity }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Pit Identifier</label>
                            <select name="pit_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($pits as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->pit_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Planned Ore Production (Tons)</label>
                            <input type="number" step="0.1" name="ore_tonnage" value="45000" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Waste Overburden Stripping (BCM)</label>
                            <input type="number" step="0.1" name="waste_overburden_bcm" value="135000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Nickel Grade (% Ni)</label>
                            <input type="number" step="0.01" name="target_grade_ni_pct" value="1.85" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Evaluate Stripping Ratio Economics
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Active Open-Pit Concessions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Pit Code</th>
                                    <th class="p-3">Pit Name</th>
                                    <th class="p-3 text-right">Target Production</th>
                                    <th class="p-3 text-right">Actual Production</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($pits as $p)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $p->pit_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $p->name }}</td>
                                        <td class="p-3 text-right font-mono text-yellow-400">{{ number_format($p->target_production_ton) }} Ton</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">{{ number_format($p->actual_production_ton) }} Ton</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-gray-500">No active pits registered.</td>
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
