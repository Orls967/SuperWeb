@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Dashboard Smart Room & Twin Energi Kamar</h1>
            <p class="text-sm text-slate-500">Penghematan daya HVAC H4 terhubung ke Core Digital Twin dan Pelaporan ESG.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <form method="POST" action="{{ route('hotel.energy.scan') }}">
                @csrf
                <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}">
                <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Pindai Sensor & Aktifkan Setback
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Energi Dihemat (Wh)</dt>
            <dd class="mt-1 text-3xl font-extrabold text-emerald-600 tabular-nums">{{ number_format($totalSavedWh, 0, ',', '.') }} Wh</dd>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Offset Emisi Karbon (Estimasi)</dt>
            <dd class="mt-1 text-3xl font-extrabold text-sky-600 tabular-nums">{{ number_format(($totalSavedWh / 1000.0) * 0.78, 2) }} kg CO2e</dd>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-slate-100 font-semibold text-slate-900">
            Riwayat Log Sensor & Setback HVAC Kamar
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="py-3 px-4 text-left font-semibold">Waktu Deteksi</th>
                    <th class="py-3 px-4 text-left font-semibold">Nomor Kamar</th>
                    <th class="py-3 px-4 text-center font-semibold">Status Setback (+3°C)</th>
                    <th class="py-3 px-4 text-right font-semibold">Durasi Setback</th>
                    <th class="py-3 px-4 text-right font-semibold">Energi Dihemat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($energyLogs as $log)
                    <tr>
                        <td class="py-3 px-4 text-slate-500 text-xs font-mono">{{ $log->event_time->format('d M Y H:i:s') }}</td>
                        <td class="py-3 px-4 font-bold text-slate-900">Kamar {{ $log->room_id }}</td>
                        <td class="py-3 px-4 text-center">
                            @if($log->setback_active)
                                <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs">AKTIF (+3°C)</span>
                            @else
                                <span class="rounded bg-slate-100 text-slate-600 px-2 py-0.5 text-xs">NORMAL</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right tabular-nums text-slate-700">{{ $log->setback_minutes }} Menit</td>
                        <td class="py-3 px-4 text-right tabular-nums font-bold text-emerald-600">{{ $log->energy_saved_wh }} Wh</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-sm text-slate-500">Belum ada riwayat setback yang tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
