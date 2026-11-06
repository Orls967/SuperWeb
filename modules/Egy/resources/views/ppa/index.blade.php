<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Kontrak PPA & Penyelesaian Finansial (Fase 126.4)') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Power Purchase Agreement (PPA), Availability Payments, Energy Settlements, & Denda Outage
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                    PPA Portfolio Managed
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

            <!-- Settlement Trigger Bar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Eksekusi Settlement PPA Bulanan</h3>
                <form action="#" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Kontrak PPA</label>
                        <select name="ppa_contract_id" required class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($contracts as $c)
                                <option value="{{ $c->id }}">{{ $c->contract_number }} - {{ $c->producer_entity_id ?? $c->generator_party_id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bulan Settlement (YYYY-MM)</label>
                        <input type="month" name="settlement_period" value="{{ now()->format('Y-m') }}" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Faktor Ketersediaan Nyata (%)</label>
                        <input type="number" name="actual_availability_pct" value="98.5" min="0" max="100" step="0.1" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Kalkulasi & Settle PPA
                        </button>
                    </div>
                </form>
            </div>

            <!-- PPA Contracts List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Portofolio Kontrak Jual-Beli Listrik (PPA)</h3>
                    <span class="text-xs text-gray-500">Total: {{ $contracts->count() }} Kontrak Aktif</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Nomor Kontrak</th>
                                <th class="px-4 py-3 text-left">IPP / Pembangkit Penjual</th>
                                <th class="px-4 py-3 text-right">Kapasitas (kW)</th>
                                <th class="px-4 py-3 text-right">Tarif Kapasitas (IDR/kW/bln)</th>
                                <th class="px-4 py-3 text-right">Tarif Energi (IDR/kWh)</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($contracts as $c)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900 font-mono">
                                        <a href="{{ route('egy.ppa.detail', $c->id) }}" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                            {{ $c->contract_number }}
                                        </a>
                                        <div class="text-2xs text-gray-400">Masa: {{ $c->valid_from }} s/d {{ $c->valid_to }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800">
                                        <div class="font-semibold">{{ $c->producer_entity_id ?? $c->generator_party_id }}</div>
                                        <div class="text-xs text-gray-500">Offtaker: {{ $c->grid_offtaker_id ?? $c->offtaker_party_id }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        {{ number_format($c->capacity_kw ?? 0) }} kW
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">
                                        Rp {{ number_format($c->rate_per_kwh_idr ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-indigo-700">
                                        Rp {{ number_format($c->rate_per_kwh_idr ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ strtoupper($c->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('egy.ppa.detail', $c->id) }}" class="text-xs text-indigo-600 hover:text-indigo-900 font-semibold">
                                            Riwayat Settlement
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 italic">
                                        Belum ada kontrak PPA yang terdaftar.
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
