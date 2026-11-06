<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Pencatatan Smart Meter & Telemetri Beban') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Smart Grid AMI (Advanced Metering Infrastructure), TOU Profiling & Anomali Ingestion (Fase 126.2)
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    AMI Ingestion Active (15m Interval)
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Ingest Manual Telemetry Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Ingest Telemetri Interval Baru (Manual Simulator)</h3>
                <form action="{{ route('egy.grid.telemetry') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Smart Meter</label>
                        <select name="smart_meter_id" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($meters as $meter)
                                <option value="{{ $meter->id }}">{{ $meter->meter_code ?? $meter->meter_serial_number }} ({{ $meter->property_id ?? 'No Property' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Mulai Interval</label>
                        <input type="datetime-local" name="interval_start" value="{{ now()->subHour()->startOfHour()->format('Y-m-d\TH:00') }}" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Energi Terpakai (Wh)</label>
                        <input type="number" name="energy_wh" value="3500" min="0" step="1" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Ingest Telemetri
                        </button>
                    </div>
                </form>
            </div>

            <!-- Smart Meter Fleet List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Daftar Smart Meter Terpasang</h3>
                    <span class="text-xs text-gray-500">Total: {{ $meters->count() }} Meter</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Nomor Meter</th>
                                <th class="px-4 py-3 text-left">Tipe Utilitas</th>
                                <th class="px-4 py-3 text-left">Node Trafo / Properti</th>
                                <th class="px-4 py-3 text-center">Status AMI</th>
                                <th class="px-4 py-3 text-right">Faktor Pengali (CT/PT)</th>
                                <th class="px-4 py-3 text-right">Bacaan Terakhir</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($meters as $m)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900 font-mono">
                                        {{ $m->meter_code ?? $m->meter_serial_number }}
                                        <div class="text-xs text-gray-400 font-sans">{{ $m->model_name ?? 'Digital AMI v2' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $m->utility_type === 'electricity' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ strtoupper($m->utility_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 text-xs">
                                        <div class="font-semibold">{{ $m->node_id ?? 'Substation Primary' }}</div>
                                        <div class="text-gray-500">ID Properti: {{ $m->property_id ?? $m->consumer_property_id ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ ($m->is_active ?? true) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ($m->is_active ?? true) ? 'ACTIVE' : 'INACTIVE' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">{{ $m->multiplier ?? 1.0 }}x</td>
                                    <td class="px-4 py-3 text-right text-xs">
                                        <div class="font-mono font-bold text-gray-900">{{ number_format($m->total_kwh_accumulated ?? 0) }} kWh</div>
                                        <div class="text-gray-400">{{ $m->updated_at ? \Carbon\Carbon::parse($m->updated_at)->diffForHumans() : 'Standar' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-xs text-indigo-600 font-semibold font-mono">
                                            {{ $m->id }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 italic">
                                        Belum ada smart meter terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Telemetry Log -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Log Bacaan Telemetri Terkini (Raw AMI Ingestion)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Waktu Interval</th>
                                <th class="px-3 py-2 text-left">Meter</th>
                                <th class="px-3 py-2 text-right">Energi Aktif (Wh)</th>
                                <th class="px-3 py-2 text-right">Daya Puncak (kW)</th>
                                <th class="px-3 py-2 text-right">Reaktif (VARh)</th>
                                <th class="px-3 py-2 text-center">Tamper Flag</th>
                                <th class="px-3 py-2 text-center">Metode Ingest</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($latestReadings as $reading)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-gray-800">
                                        {{ \Carbon\Carbon::parse($reading->interval_start)->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-gray-700">
                                        {{ $reading->meter->meter_number ?? $reading->smart_meter_id }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-gray-900">
                                        {{ number_format($reading->energy_wh) }} Wh
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-indigo-700">
                                        {{ number_format($reading->peak_kw, 2) }} kW
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-500">
                                        {{ number_format($reading->reactive_energy_varh ?? 0) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if($reading->tamper_flag)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-rose-100 text-rose-800">
                                                TAMPER!
                                            </span>
                                        @else
                                            <span class="text-emerald-600 font-mono">OK</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-gray-500">
                                        {{ $reading->quality_flag ?? 'verified' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-center text-gray-400 italic">
                                        Belum ada telemetri yang tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
