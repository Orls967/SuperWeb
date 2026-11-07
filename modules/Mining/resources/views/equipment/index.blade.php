@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Heavy Equipment Telemetry & OEE Peringkat</h2>
                    <p class="text-sm text-gray-400">Monitoring telemetri sensor (suhu engine, tekanan hidrolik) dan status armada tambang.</p>
                </div>
            </div>

            <!-- Daftar Armada & Metrik OEE -->
            <div class="bg-gray-700/40 rounded-lg p-6 border border-gray-600">
                <h3 class="text-lg font-semibold text-white mb-4">Armada Tambang Aktif & Telemetri Terakhir</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="p-3">Kode Alat</th>
                                <th class="p-3">Nama</th>
                                <th class="p-3">Tipe</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Jam Operasi (HM)</th>
                                <th class="p-3">OEE Est.</th>
                                <th class="p-3">Telemetri Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($equipment as $eq)
                                @php
                                    $latestTel = $eq->telemetries->first();
                                @endphp
                                <tr class="hover:bg-gray-750">
                                    <td class="p-3 font-mono font-medium text-amber-400">{{ $eq->code }}</td>
                                    <td class="p-3">{{ $eq->name }}</td>
                                    <td class="p-3">{{ $eq->type }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-gray-900 border border-gray-600 text-gray-300">
                                            {{ $eq->status }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ number_format($eq->hour_meter_current, 1) }} Jam</td>
                                    <td class="p-3 font-mono text-emerald-400">
                                        {{ number_format((float)($eq->oee_availability * $eq->oee_performance * $eq->oee_quality * 100), 1) }}%
                                    </td>
                                    <td class="p-3 text-xs text-gray-400">
                                        @if($latestTel)
                                            Engine: {{ $latestTel->engine_temp_c }}°C | Hyd: {{ $latestTel->hydraulic_pressure_bar }} bar
                                        @else
                                            Belum ada log
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-gray-400">Belum ada armada berat yang terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $equipment->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
