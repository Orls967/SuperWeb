@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🚛</span> Haul Truck Dispatch &amp; Fuel Telematics Anomaly
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Payload variance monitoring, haul cycle distance tracking, and specific fuel consumption theft alerts.
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
                <div class="mb-4 p-4 bg-rose-900/60 border border-rose-500 rounded-lg text-rose-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Record Haul Dispatch Run</h3>
                    <form action="{{ route('mining.operations.haul-dispatch.truck') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Source Pit</label>
                            <select name="pit_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($pits as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Haul Truck</label>
                            <select name="equipment_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($equipments as $eq)
                                    <option value="{{ $eq->id }}">{{ $eq->equipment_code }} ({{ $eq->model }} &ndash; {{ $eq->capacity_ton }}T)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Shift</label>
                            <select name="shift_code" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="SHIFT_A">Shift A (Morning)</option>
                                <option value="SHIFT_B">Shift B (Night)</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Target (Ton)</label>
                                <input type="number" step="0.1" name="target_payload_ton" value="40.0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Actual (Ton)</label>
                                <input type="number" step="0.1" name="actual_payload_ton" value="41.2" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Fuel (Liters)</label>
                                <input type="number" step="0.1" name="fuel_consumed_liter" value="22.5" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 mb-1">Distance (km)</label>
                                <input type="number" step="0.1" name="distance_km" value="12.0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Log Telematics Dispatch Trip
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Completed Haul Trips</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Shift</th>
                                    <th class="p-3 text-right">Payload</th>
                                    <th class="p-3 text-right">Variance</th>
                                    <th class="p-3 text-right">Fuel Consumed</th>
                                    <th class="p-3 text-center">Anomaly</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($runs as $r)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $r->shift_code }}</td>
                                        <td class="p-3 text-right font-mono text-white">{{ $r->actual_payload_ton }} Ton</td>
                                        <td class="p-3 text-right font-mono {{ $r->payload_variance_ton >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $r->payload_variance_ton }} Ton</td>
                                        <td class="p-3 text-right font-mono">{{ $r->fuel_consumed_liter }} L ({{ $r->distance_km }} km)</td>
                                        <td class="p-3 text-center">
                                            @if($r->fuel_anomaly_detected)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-900 text-rose-300 border border-rose-700">ANOMALY</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">NORMAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No haul runs recorded yet.</td>
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
