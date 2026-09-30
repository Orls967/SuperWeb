<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Pencatatan Meteran Utilitas (Listrik & Air)
                </h2>
                <p class="text-sm text-slate-500 mt-1">Input pembacaan meteran berkala per unit tenant dengan perhitungan tarif berjenjang otomatis</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Tariffs Card & Filter -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Filter Card -->
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <h4 class="font-bold text-slate-800 text-sm mb-3">Pilih Periode & Properti</h4>
                    <form method="GET" action="{{ route('mall.utilities.index') }}" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Periode Bulan</label>
                            <input type="month" name="month" value="{{ $period }}" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Mall Properti</label>
                            <select name="property_id" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2">
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}" {{ $propertyId == $prop->id ? 'selected' : '' }}>{{ $prop->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition">
                            Tampilkan Unit
                        </button>
                    </form>
                </div>

                <!-- Electricity Tariff Info -->
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center gap-2 mb-2 text-amber-600 font-bold text-xs uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Skema Tarif Listrik Berjenjang</span>
                    </div>
                    <div class="space-y-1.5 text-xs text-slate-600 mt-2">
                        @php
                            $elecTariffs = $tariffs->where('utility_type.value', 'electricity');
                        @endphp
                        @forelse($elecTariffs as $t)
                            <div class="flex justify-between border-b border-slate-50 pb-1">
                                <span>Tier {{ $t->tier_number }} ({{ $t->tier_min }} - {{ $t->tier_max ?? '&infin;' }} kWh):</span>
                                <span class="font-bold text-slate-800">Rp {{ number_format($t->rate_per_unit) }}/kWh</span>
                            </div>
                        @empty
                            <p class="text-slate-400">Tarif default: Rp 1.650 / kWh</p>
                        @endforelse
                        @if($elecTariffs->isNotEmpty() && $elecTariffs->first()->standing_charge > 0)
                            <div class="flex justify-between text-slate-500 pt-1">
                                <span>Beban Tetap (Abonemen):</span>
                                <span class="font-bold">Rp {{ number_format($elecTariffs->first()->standing_charge) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Water Tariff Info -->
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center gap-2 mb-2 text-cyan-600 font-bold text-xs uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        <span>Skema Tarif Air PDAM Berjenjang</span>
                    </div>
                    <div class="space-y-1.5 text-xs text-slate-600 mt-2">
                        @php
                            $waterTariffs = $tariffs->where('utility_type.value', 'water');
                        @endphp
                        @forelse($waterTariffs as $t)
                            <div class="flex justify-between border-b border-slate-50 pb-1">
                                <span>Tier {{ $t->tier_number }} ({{ $t->tier_min }} - {{ $t->tier_max ?? '&infin;' }} m³):</span>
                                <span class="font-bold text-slate-800">Rp {{ number_format($t->rate_per_unit) }}/m³</span>
                            </div>
                        @empty
                            <p class="text-slate-400">Tarif default: Rp 9.000 / m³</p>
                        @endforelse
                        @if($waterTariffs->isNotEmpty() && $waterTariffs->first()->standing_charge > 0)
                            <div class="flex justify-between text-slate-500 pt-1">
                                <span>Beban Tetap (Abonemen):</span>
                                <span class="font-bold">Rp {{ number_format($waterTariffs->first()->standing_charge) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Batch Meter Input Table -->
            <form action="{{ route('mall.utilities.batch') }}" method="POST">
                @csrf
                <input type="hidden" name="period_month" value="{{ $period }}">
                <input type="hidden" name="property_id" value="{{ $propertyId }}">

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Pencatatan Meteran Unit Tenant — Periode {{ $period }}</h3>
                            <p class="text-xs text-slate-400">Masukkan angka meteran saat ini, pemakaian dan nominal akan dihitung otomatis saat disimpan</p>
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Simpan & Hitung Tagihan</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-100">
                                    <th class="py-3 px-4" rowspan="2">Unit & Tenant</th>
                                    <th class="py-2 px-4 text-center bg-amber-50/60 text-amber-800 border-b border-amber-100" colspan="3">Listrik (kWh)</th>
                                    <th class="py-2 px-4 text-center bg-cyan-50/60 text-cyan-800 border-b border-cyan-100" colspan="3">Air PDAM (m³)</th>
                                </tr>
                                <tr class="bg-slate-50/50 text-slate-400 text-[10px] border-b border-slate-100">
                                    <th class="py-2 px-3 text-right bg-amber-50/20">Meter Lalu</th>
                                    <th class="py-2 px-3 text-center bg-amber-50/20">Meter Kini</th>
                                    <th class="py-2 px-3 text-right bg-amber-50/20">Pemakaian</th>
                                    <th class="py-2 px-3 text-right bg-cyan-50/20">Meter Lalu</th>
                                    <th class="py-2 px-3 text-center bg-cyan-50/20">Meter Kini</th>
                                    <th class="py-2 px-3 text-right bg-cyan-50/20">Pemakaian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse($leases as $lease)
                                    @php
                                        $elecReading = $existingReadings[$lease->id]['electricity'][0] ?? null;
                                        $waterReading = $existingReadings[$lease->id]['water'][0] ?? null;
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-800">Unit {{ $lease->unit?->unit_number }}</div>
                                            <div class="text-[11px] text-slate-500">{{ $lease->tenant?->brand_name }}</div>
                                            <span class="text-[10px] text-slate-400 font-mono">Lt. {{ $lease->unit?->floor }}</span>
                                        </td>

                                        <!-- Electricity Inputs -->
                                        <td class="py-3 px-3 text-right bg-amber-50/10">
                                            <input type="number" step="0.01" name="readings[{{ $lease->id }}][electricity][previous_meter]"
                                                value="{{ old("readings.{$lease->id}.electricity.previous_meter", $elecReading?->previous_meter ?? 0) }}"
                                                class="w-20 text-xs text-right rounded-lg border-slate-200 py-1 px-2 bg-slate-50 font-mono">
                                        </td>
                                        <td class="py-3 px-3 text-center bg-amber-50/10">
                                            <input type="number" step="0.01" name="readings[{{ $lease->id }}][electricity][current_meter]"
                                                value="{{ old("readings.{$lease->id}.electricity.current_meter", $elecReading?->current_meter) }}"
                                                placeholder="0.00"
                                                class="w-24 text-xs text-right rounded-lg border-amber-300 py-1 px-2 font-mono font-bold text-amber-900 focus:ring-amber-500">
                                        </td>
                                        <td class="py-3 px-3 text-right bg-amber-50/10 font-mono font-medium text-amber-800">
                                            {{ $elecReading ? number_format($elecReading->usage, 1) . ' kWh' : '-' }}
                                            @if($elecReading)
                                                <div class="text-[10px] text-slate-400">Rp {{ number_format($elecReading->amount) }}</div>
                                            @endif
                                        </td>

                                        <!-- Water Inputs -->
                                        <td class="py-3 px-3 text-right bg-cyan-50/10">
                                            <input type="number" step="0.01" name="readings[{{ $lease->id }}][water][previous_meter]"
                                                value="{{ old("readings.{$lease->id}.water.previous_meter", $waterReading?->previous_meter ?? 0) }}"
                                                class="w-20 text-xs text-right rounded-lg border-slate-200 py-1 px-2 bg-slate-50 font-mono">
                                        </td>
                                        <td class="py-3 px-3 text-center bg-cyan-50/10">
                                            <input type="number" step="0.01" name="readings[{{ $lease->id }}][water][current_meter]"
                                                value="{{ old("readings.{$lease->id}.water.current_meter", $waterReading?->current_meter) }}"
                                                placeholder="0.00"
                                                class="w-24 text-xs text-right rounded-lg border-cyan-300 py-1 px-2 font-mono font-bold text-cyan-900 focus:ring-cyan-500">
                                        </td>
                                        <td class="py-3 px-3 text-right bg-cyan-50/10 font-mono font-medium text-cyan-800">
                                            {{ $waterReading ? number_format($waterReading->usage, 1) . ' m³' : '-' }}
                                            @if($waterReading)
                                                <div class="text-[10px] text-slate-400">Rp {{ number_format($waterReading->amount) }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-slate-400">
                                            Tidak ada kontrak lease aktif pada properti ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
