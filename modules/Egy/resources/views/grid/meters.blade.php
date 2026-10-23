@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Smart Meter Telemetry Ingestion</h2>
                    <p class="mt-1 text-sm text-gray-400">Pencatatan Telemetri IoT Konsumsi Energi & Peak Demand</p>
                </div>
                <a href="{{ route('egy.grid.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali ke Grid</a>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Ingest Telemetri Meter Manual / Uji Coba</h3>
                <form action="{{ route('egy.grid.telemetry') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Nomor Meter</label>
                        <input type="text" name="meter_number" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="MTR-001">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Konsumsi (kWh)</label>
                        <input type="number" name="kwh_consumed" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="125">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Peak Demand (kW)</label>
                        <input type="number" name="peak_demand_kw" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="18">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                            Kirim Telemetri
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6">
                <h3 class="text-md font-medium text-white mb-3">Telemetri Terkini Terbaca ({{ $recentReadings->count() }})</h3>
                <div class="space-y-2">
                    @forelse($recentReadings as $reading)
                        <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center text-xs">
                            <span class="font-mono text-white">Meter: {{ $reading->smart_meter_id }}</span>
                            <span class="text-emerald-400 font-semibold">{{ $reading->kwh_consumed }} kWh</span>
                            <span class="text-gray-400">Peak: {{ $reading->peak_demand_kw }} kW</span>
                            <span class="text-gray-500">{{ $reading->reading_timestamp }}</span>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada data bacaan meter.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
