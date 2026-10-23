@extends('layouts.app')

@section('title', 'Telematics Rolling Baselines')
@section('subtitle', 'Analisis 7-Hari Rolling Baseline Suhu Oli, Tegangan Aki, dan Konsumsi BBM')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">Rolling Baseline Kendaraan</h1>
            <p class="text-sm text-slate-400">Total Kendaraan Terpantau: {{ $totalVehiclesMonitored }} Unit</p>
        </div>
        <a href="{{ route('telematics.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-200 bg-slate-800/80 hover:bg-slate-700/80 rounded-xl border border-slate-700">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-4">Vehicle ID</th>
                        <th class="p-4">Rata-rata Suhu Oli (°C)</th>
                        <th class="p-4">Rata-rata Tegangan Aki (V)</th>
                        <th class="p-4">Tingkat BBM (%)</th>
                        <th class="p-4">Jumlah Sampel</th>
                        <th class="p-4">Terakhir Dihitung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($baselines as $b)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-4 font-mono font-bold text-violet-400">VEH-{{ $b->vehicle_id }}</td>
                            <td class="p-4 font-semibold {{ $b->avg_oil_temp_c > 105 ? 'text-rose-400' : 'text-slate-200' }}">
                                {{ $b->avg_oil_temp_c }} °C
                            </td>
                            <td class="p-4 font-semibold {{ $b->avg_battery_voltage < 12.0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                {{ $b->avg_battery_voltage }} V
                            </td>
                            <td class="p-4">{{ $b->avg_fuel_consumption_rate }}%</td>
                            <td class="p-4 font-mono">{{ number_format($b->sample_count) }}</td>
                            <td class="p-4 text-xs text-slate-400">{{ optional($b->calculated_at)->format('d M Y H:i:s') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">Belum ada data baseline rolling.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $baselines->links() }}
        </div>
    </div>
</div>
@endsection
