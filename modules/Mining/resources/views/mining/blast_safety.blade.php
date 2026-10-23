@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>💥</span> Blast Radius Exclusion &amp; Geotech Safety Lock
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 94: Pit drilling &amp; blasting clearance interlock. Automatically locks initiation if personnel are in the 500m danger radius.
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

            @if(session('error'))
                <div class="mb-4 p-4 bg-rose-900/60 border border-rose-500 rounded-lg text-rose-200 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Validate Blast Initiation</h3>
                    <form action="{{ route('mining.operations.blast-safety.lock') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Pit Zone</label>
                            <select name="pit_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($pits as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->pit_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Blast Pattern Code</label>
                            <input type="text" name="blast_code" value="BLAST-{{ strtoupper(substr(uniqid(), -5)) }}" maxlength="32" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Latitude</label>
                                <input type="number" step="0.000001" name="blast_coord_lat" value="-2.531200" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Longitude</label>
                                <input type="number" step="0.000001" name="blast_coord_lng" value="121.341100" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Blast Holes Drilled</label>
                            <input type="number" name="holes_count" value="48" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Total Explosive Weight (ANFO / Emulsion Kg)</label>
                            <input type="number" step="0.1" name="explosives_kg" value="2400.0" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-rose-300 mb-1">IoT Detected Personnel Inside 500m Radius</label>
                            <input type="number" name="active_personnel_in_radius" value="0" min="0" required class="w-full bg-gray-800 border border-rose-700 rounded p-2 text-white text-sm font-bold">
                            <p class="text-[11px] text-gray-400 mt-1">Safety Rule: Must be 0 to trigger detonation green light.</p>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 font-semibold rounded text-white text-sm transition">
                            Verify Clearance &amp; Arm Blast
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Blast Clearance Registry</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Blast Code</th>
                                    <th class="p-3">Pit</th>
                                    <th class="p-3">Radius</th>
                                    <th class="p-3">Holes</th>
                                    <th class="p-3">Explosives (Kg)</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 font-mono">
                                @forelse($schedules as $b)
                                    <tr class="hover:bg-gray-800/50">
                                        <td class="p-3 font-semibold text-white">{{ $b->blast_code }}</td>
                                        <td class="p-3">{{ $b->pit->name ?? substr($b->pit_id, 0, 8) }}</td>
                                        <td class="p-3">{{ $b->safety_radius_meters }}m</td>
                                        <td class="p-3">{{ $b->holes_count }}</td>
                                        <td class="p-3 text-cyan-400">{{ number_format($b->explosives_kg, 1) }}</td>
                                        <td class="p-3">
                                            @if($b->status === 'SCHEDULED')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
                                                    SCHEDULED (ARMED)
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-950 text-rose-400 border border-rose-800">
                                                    BLOCKED (INTERLOCK)
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No blast schedules logged yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $schedules->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
