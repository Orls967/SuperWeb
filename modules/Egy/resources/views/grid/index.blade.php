@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Smart Grid Operations & Transformer Load</h2>
            <p class="mt-1 text-sm text-gray-400">Monitoring Jaringan Listrik Pintar, Gardu Distribusi & Anomali Beban</p>

            @if(session('status_report'))
                <div class="mt-4 p-4 bg-indigo-900/50 border border-indigo-500 rounded text-indigo-200 text-sm">
                    {{ session('status_report') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Grid Nodes -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Gardu Distribusi & Trafo ({{ $nodes->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($nodes as $node)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="text-white font-medium">{{ $node->node_code }} ({{ $node->location_name }})</div>
                                        <div class="text-xs text-gray-400">Tegangan: {{ $node->voltage_kv }} kV • Kapasitas: {{ $node->transformer_capacity_kva }} kVA</div>
                                        <div class="text-xs text-indigo-300 mt-1">Beban Saat Ini: {{ $node->current_load_kva }} kVA</div>
                                    </div>
                                    <form action="{{ route('egy.grid.anomaly', $node->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="py-1 px-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded transition">
                                            Scan Anomali
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data gardu grid.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Meters Overview -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-md font-medium text-gray-200">Smart Meters Terpasang ({{ $meters->count() }})</h3>
                        <a href="{{ route('egy.grid.meters') }}" class="text-xs text-indigo-400 hover:underline">Lihat Semua Meter &rarr;</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($meters as $meter)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <span class="font-mono text-white text-sm">{{ $meter->meter_number }}</span>
                                    <div class="text-xs text-gray-400">Tarif: {{ $meter->tariff_code }} • Pelanggan: {{ $meter->customer_party_id }}</div>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $meter->status }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada meter aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
