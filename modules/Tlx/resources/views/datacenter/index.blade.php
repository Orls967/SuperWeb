@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Data Center Colocation & PUE Efficiency</h2>
                    <p class="mt-1 text-sm text-gray-400">Manajemen Fasilitas Edge/Hyperscale Colocation & Pengukuran PUE</p>
                </div>
                <a href="{{ route('tlx.datacenter.racks') }}" class="text-sm text-indigo-400 hover:underline">Lihat Detail Racks &rarr;</a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Data Center Facilities -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-gray-700/50 p-6 rounded-lg">
                        <h3 class="text-lg font-medium text-white mb-4">Fasilitas Data Center ({{ $datacenters->count() }})</h3>
                        <div class="space-y-4">
                            @forelse($datacenters as $dc)
                                <div class="p-4 bg-gray-800 rounded border border-gray-600">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="text-white font-semibold">{{ $dc->name }} ({{ $dc->dc_code }})</div>
                                            <div class="text-xs text-gray-400 mt-0.5">Lokasi: {{ $dc->city }} • Total Kapasitas: {{ $dc->total_racks_capacity }} Racks</div>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-2 py-0.5 text-xs rounded {{ $dc->measured_pue <= 1.5 ? 'bg-emerald-900 text-emerald-200' : 'bg-rose-900 text-rose-200' }}">
                                                PUE: {{ number_format($dc->measured_pue, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex justify-between text-xs text-gray-300">
                                        <span>Terisi: {{ $dc->occupied_racks }} / {{ $dc->total_racks_capacity }} Racks</span>
                                        <span>Daya: {{ $dc->it_load_power_kw }} kW / {{ $dc->total_facility_power_kw }} kW</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-400 text-sm">Belum ada fasilitas data center terdaftar.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Colocation Contracts -->
                    <div class="bg-gray-700/50 p-6 rounded-lg">
                        <h3 class="text-lg font-medium text-white mb-4">Kontrak Colocation Terakhir ({{ $contracts->count() }})</h3>
                        <div class="space-y-3">
                            @forelse($contracts as $contract)
                                <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                    <div>
                                        <div class="text-white font-mono text-sm">{{ $contract->contract_number }}</div>
                                        <div class="text-xs text-gray-400">Tenant: {{ $contract->tenant_party_id }} • Alokasi: {{ $contract->rack_units_allocated }} RU</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-emerald-400 text-sm">Rp {{ number_format($contract->monthly_rack_fee_minor, 0, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400">Port: {{ $contract->port_status }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-400 text-sm">Belum ada kontrak colocation aktif.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Forms: Rack Allocation & PUE Audit -->
                <div class="space-y-6">
                    <div class="bg-gray-700/50 p-6 rounded-lg">
                        <h3 class="text-lg font-medium text-white mb-4">Alokasi Rack Colocation</h3>
                        <form action="{{ route('tlx.datacenter.racks.allocate') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-300">Data Center</label>
                                <select name="dc_id" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                                    @foreach($datacenters as $dc)
                                        <option value="{{ $dc->id }}">{{ $dc->name }} (Sisa: {{ $dc->total_racks_capacity - $dc->occupied_racks }} Racks)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-300">Tenant Party ID</label>
                                <input type="text" name="tenant_party_id" required value="TNT-FINTECH-01" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-300">Alokasi RU (Rack Units)</label>
                                <input type="number" name="rack_units_allocated" required min="1" max="42" value="2" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-300">Biaya Bulanan (Minor IDR)</label>
                                <input type="number" name="monthly_rack_fee_minor" required min="0" value="30000000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                            </div>
                            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold">
                                Buat Kontrak Alokasi
                            </button>
                        </form>
                    </div>

                    <div class="bg-gray-700/50 p-6 rounded-lg">
                        <h3 class="text-lg font-medium text-white mb-4">Audit PUE & Evaluasi SLA</h3>
                        <form action="{{ route('tlx.datacenter.pue.enforce') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-300">Data Center</label>
                                <select name="dc_id" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                                    @foreach($datacenters as $dc)
                                        <option value="{{ $dc->id }}">{{ $dc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-300">Hasil Ukur PUE</label>
                                <input type="number" step="0.01" name="measured_pue" required min="1.0" max="3.0" value="1.65" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                            </div>
                            <button type="submit" class="w-full py-2 bg-rose-600 hover:bg-rose-500 text-white rounded text-xs font-semibold">
                                Evaluasi Pelanggaran SLA
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
