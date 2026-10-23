@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Mining" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Mining Operations & Fleet Dispatch</h2>
            <p class="mt-1 text-sm text-gray-400">Manajemen Site, Pit, Equipment & Dispatch Run Terintegrasi</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Sites & Pits -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Mining Sites & Pits</h3>
                    <div class="space-y-2">
                        @forelse($pits as $pit)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $pit->pit_code }} — {{ $pit->name }}</div>
                                    <div class="text-xs text-gray-400">Target: {{ number_format((float)$pit->target_daily_production_ton) }} ton • Actual: {{ number_format((float)$pit->actual_production_ton) }} ton</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data pit.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Equipment -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Equipment Fleet</h3>
                    <div class="space-y-2">
                        @forelse($equipment as $equip)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $equip->equipment_code }} ({{ $equip->type }})</div>
                                    <div class="text-xs text-gray-400">Engine Hours: {{ number_format($equip->engine_hours) }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $equip->status === 'available' ? 'bg-emerald-900 text-emerald-200' : 'bg-amber-900 text-amber-200' }}">
                                        {{ $equip->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada equipment terdaftar.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Dispatch Runs -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Dispatch Runs Terkini</h3>
                <div class="space-y-2">
                    @forelse($dispatches as $run)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                            <div>
                                <div class="text-white font-medium">Shift {{ $run->shift_code }}</div>
                                <div class="text-xs text-gray-400">Target: {{ $run->target_payload_ton }} ton • Actual: {{ $run->actual_payload_ton }} ton • Fuel: {{ $run->fuel_consumed_liter }} L</div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $run->status === 'completed' ? 'bg-emerald-900 text-emerald-200' : 'bg-blue-900 text-blue-200' }}">
                                    {{ $run->status }}
                                </span>
                                @if($run->fuel_anomaly_detected)
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-red-900 text-red-200 ml-1">ANOMALY</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada dispatch run.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
