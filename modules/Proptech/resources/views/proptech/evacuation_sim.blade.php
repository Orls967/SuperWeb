@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🚨</span> Emergency Evacuation Dynamic Egress Simulation
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Computational crowd clearance, egress flow bottleneck detection, and life-safety compliance.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('proptech.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Proptech Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Run Spatial Evacuation Scenario</h3>
                    <form action="{{ route('proptech.intelligence.evacuation.run') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Building Zone</label>
                            <select name="zone_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($zones as $z)
                                    <option value="{{ $z->id }}">{{ $z->name }} (Floor: {{ $z->floor_level }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Hazard Trigger Scenario</label>
                            <select name="hazard_type" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="FIRE">Structural Fire (Thermal & Smoke Spread)</option>
                                <option value="GAS_LEAK">Toxic / Flammable Gas Leak</option>
                                <option value="EARTHQUAKE">Seismic Ground Motion Event</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Zone Occupancy Load</label>
                            <input type="number" name="initial_occupancy" value="85" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-semibold rounded-lg transition">
                            Run Dynamic Egress Simulation
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">NFPA 101 Egress Benchmarks</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&le; 120 Seconds</span>
                                <p class="text-xs text-gray-400">Optimal rapid egress without bottlenecking</p>
                            </div>
                            <span class="px-2.5 py-1 bg-emerald-900 text-emerald-300 font-bold text-xs rounded border border-emerald-700">Grade A</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">121 &ndash; 180 Seconds</span>
                                <p class="text-xs text-gray-400">Acceptable clearance with standard staircase delay</p>
                            </div>
                            <span class="px-2.5 py-1 bg-cyan-900 text-cyan-300 font-bold text-xs rounded border border-cyan-700">Grade B</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&gt; 180 Seconds</span>
                                <p class="text-xs text-gray-400">Elevated bottleneck risk requiring architectural mitigation</p>
                            </div>
                            <span class="px-2.5 py-1 bg-rose-900 text-rose-300 font-bold text-xs rounded border border-rose-700">Grade C/F</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
