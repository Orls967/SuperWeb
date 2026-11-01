@extends('layouts.app')

@section('title', 'Telematics Critical DTC Alerts & Grounding')
@section('subtitle', 'Monitoring Kerusakan Mesin OBD-II dan Kebijakan Grounding Otomatis')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 backdrop-blur-xl">
            <p class="text-xs font-semibold text-rose-400 uppercase tracking-wider">Unit Grounded (Kritis)</p>
            <p class="text-3xl font-bold text-rose-300 mt-1">{{ $groundedDevices->count() }}</p>
            <p class="text-xs text-rose-300/70 mt-1">Operasional unit dibekukan demi keselamatan</p>
        </div>

        <div class="md:col-span-2 p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-sm font-bold text-slate-200 mb-3">Simulasi Injeksi Kerusakan OBD-II</h3>
            <form action="{{ route('telematics.operations.evaluate-dtc') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <input type="text" name="device_id" placeholder="Device ID (misal: DEV-001)" required class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                <input type="text" name="dtc_code" placeholder="Kode DTC (misal: P0217)" required class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono">
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold rounded-xl">Evaluasi DTC</button>
            </form>
        </div>
    </div>

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-lg font-bold text-slate-100 mb-4">Log Riwayat DTC & Kerusakan OBD-II</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-4">Waktu</th>
                        <th class="p-4">Device ID</th>
                        <th class="p-4">Vehicle ID</th>
                        <th class="p-4">Kode DTC</th>
                        <th class="p-4">Suhu Oli</th>
                        <th class="p-4">Tegangan Aki</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($criticalTicks as $t)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-4 text-xs text-slate-400">{{ optional($t->recorded_at)->format('d M H:i:s') }}</td>
                            <td class="p-4 font-mono text-slate-200">{{ $t->device_id }}</td>
                            <td class="p-4 font-mono text-violet-400">VEH-{{ $t->vehicle_id }}</td>
                            <td class="p-4 font-mono font-bold text-rose-400 bg-rose-500/10 rounded-md px-2 py-0.5 inline-block">
                                {{ $t->dtc_code }}
                            </td>
                            <td class="p-4 text-slate-200">{{ $t->oil_temp_c }} °C</td>
                            <td class="p-4 text-slate-200">{{ $t->battery_voltage }} V</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">Tidak ada alarm DTC saat ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $criticalTicks->links() }}
        </div>
    </div>
</div>
@endsection
