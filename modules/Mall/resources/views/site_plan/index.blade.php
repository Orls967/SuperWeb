<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Site Plan & Denah Unit Mall
                </h2>
                <p class="text-sm text-slate-500 mt-1">Peta interaktif okupansi tenant, status ketersediaan ruang komersial, dan Gross Leasable Area (GLA)</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('mall.leases.create') }}" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Buat Sewa (Lease) Baru</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ selectedUnit: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Top Occupancy KPI Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Okupansi Mall (GLA)</span>
                        <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-slate-900">
                            {{ $occupancy['occupancy_rate_percent'] }}%
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 mt-2 overflow-hidden">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $occupancy['occupancy_rate_percent']) }}%"></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">{{ number_format($occupancy['leased_gla_sqm'], 1, ',', '.') }} m² dari {{ number_format($occupancy['total_gla_sqm'], 1, ',', '.') }} m²</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Unit Tersewa (Leased)</span>
                        <div class="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-indigo-600">
                            {{ $occupancy['leased_units'] }} <span class="text-base font-medium text-slate-500">/ {{ $occupancy['total_units'] }} Unit</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Tenant aktif beroperasi</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Unit Tersedia (Vacant)</span>
                        <div class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-emerald-600">
                            {{ $occupancy['available_units'] }} Unit
                        </div>
                        <p class="text-xs text-slate-400 mt-1">{{ number_format($occupancy['available_gla_sqm'], 1, ',', '.') }} m² siap dipasarkan</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Segera Habis (< 90 Hari)</span>
                        <div class="p-2 rounded-xl bg-amber-50 text-amber-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-amber-600">
                            {{ count($occupancy['expiring_soon_leases']) }} Kontrak
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Perlu perpanjangan (renewal)</p>
                    </div>
                </div>
            </div>

            <!-- Floor Selector & Site Plan Layout -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Lantai Mall:</span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($availableFloors as $fl)
                                <a href="{{ route('mall.site-plan.index', ['floor' => $fl, 'property_id' => $selectedProperty?->id]) }}"
                                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all
                                   {{ $selectedFloor === $fl ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                    Lantai {{ $fl }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="flex items-center gap-4 text-xs font-semibold text-slate-600 flex-wrap">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span>Tersedia (Vacant)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span>
                            <span>Tersewa (Leased)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                            <span>Dipesan (Reserved)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
                            <span>Maintenance</span>
                        </div>
                    </div>
                </div>

                <!-- Unit Grid Floor Plan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @forelse($units as $unit)
                        @php
                            $activeLease = $unit->activeLease;
                            $tenant = $activeLease?->tenant;
                        @endphp
                        <div @click="selectedUnit = {{ json_encode([
                                'id' => $unit->id,
                                'number' => $unit->unit_number,
                                'floor' => $unit->floor,
                                'area' => $unit->area_sqm,
                                'rate' => $unit->base_rent_rate_per_sqm,
                                'sc' => $unit->service_charge_per_sqm,
                                'status' => $unit->status->value,
                                'status_label' => $unit->status->label(),
                                'tenant' => $tenant ? $tenant->brand_name : null,
                                'company' => $tenant ? $tenant->company_name : null,
                                'lease_end' => $activeLease ? $activeLease->end_date->format('d/m/Y') : null,
                                'lease_id' => $activeLease ? $activeLease->id : null,
                            ]) }}"
                             class="p-4 rounded-2xl border transition-all cursor-pointer hover:shadow-md hover:scale-[1.01] flex flex-col justify-between
                             {{ $unit->status->value === 'leased' ? 'border-blue-200 bg-blue-50/40 hover:border-blue-300' : '' }}
                             {{ $unit->status->value === 'available' ? 'border-emerald-200 bg-emerald-50/40 hover:border-emerald-300' : '' }}
                             {{ $unit->status->value === 'reserved' ? 'border-amber-200 bg-amber-50/40 hover:border-amber-300' : '' }}
                             {{ $unit->status->value === 'maintenance' ? 'border-rose-200 bg-rose-50/40 hover:border-rose-300' : '' }}">

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-mono font-black text-base text-slate-800">{{ $unit->unit_number }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $unit->status->badgeClass() }}">
                                        {{ $unit->status->label() }}
                                    </span>
                                </div>

                                @if($tenant)
                                    <div class="font-bold text-sm text-blue-900 truncate">{{ $tenant->brand_name }}</div>
                                    <div class="text-[11px] text-slate-500 truncate">{{ $tenant->category->label() }}</div>
                                @else
                                    <div class="font-semibold text-sm text-slate-400 italic">Belum Ada Tenant</div>
                                    <div class="text-[11px] text-slate-400">{{ $unit->zone?->name ?? 'Zona Reguler' }}</div>
                                @endif
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                                <span class="font-medium">{{ $unit->area_sqm }} m²</span>
                                <span class="font-mono font-bold text-slate-700">Rp {{ number_format($unit->estimatedBaseRent() / 1000000, 1) }}jt<span class="text-[10px] font-normal text-slate-400">/bln</span></span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full p-12 text-center text-slate-400 italic">
                            Belum ada denah unit komersial terdaftar di lantai {{ $selectedFloor }}.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Modal Detail Unit (Alpine.js) -->
            <div x-show="selectedUnit" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div x-show="selectedUnit" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="selectedUnit = null"></div>

                <div class="flex min-h-full items-center justify-center p-4">
                    <div x-show="selectedUnit" x-transition class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="font-black text-lg text-slate-900" x-text="'Unit ' + selectedUnit?.number"></h3>
                                <p class="text-xs text-slate-400" x-text="'Lantai ' + selectedUnit?.floor + ' &bull; Luas: ' + selectedUnit?.area + ' m²'"></p>
                            </div>
                            <button @click="selectedUnit = null" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                        </div>

                        <div class="space-y-3 text-sm">
                            <div class="p-3 rounded-xl bg-slate-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">Status Ruang</span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold" x-text="selectedUnit?.status_label"></span>
                            </div>

                            <template x-if="selectedUnit?.tenant">
                                <div class="p-4 rounded-xl border border-blue-100 bg-blue-50/50 space-y-2">
                                    <div class="text-xs font-bold text-blue-800 uppercase">Tenant Penyewa Aktif</div>
                                    <div class="text-base font-black text-slate-900" x-text="selectedUnit?.tenant"></div>
                                    <div class="text-xs text-slate-500" x-text="'Perusahaan: ' + selectedUnit?.company"></div>
                                    <div class="text-xs text-slate-500" x-text="'Berakhir: ' + selectedUnit?.lease_end"></div>
                                </div>
                            </template>

                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="p-3 rounded-xl border border-slate-100">
                                    <span class="text-slate-400 block mb-1">Tarif Sewa Dasar</span>
                                    <span class="font-bold text-slate-800 font-mono" x-text="'Rp ' + Number(selectedUnit?.rate).toLocaleString('id-ID') + ' /m²/bln'"></span>
                                </div>
                                <div class="p-3 rounded-xl border border-slate-100">
                                    <span class="text-slate-400 block mb-1">Service Charge</span>
                                    <span class="font-bold text-slate-800 font-mono" x-text="'Rp ' + Number(selectedUnit?.sc).toLocaleString('id-ID') + ' /m²/bln'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="selectedUnit = null" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                Tutup
                            </button>
                            <template x-if="selectedUnit?.status === 'available'">
                                <a :href="'{{ route('mall.leases.create') }}?unit_id=' + selectedUnit?.id" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20">
                                    Sewa Unit Ini &rarr;
                                </a>
                            </template>
                            <template x-if="selectedUnit?.lease_id">
                                <a :href="'/mall/leases/' + selectedUnit?.lease_id" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20">
                                    Lihat Kontrak &rarr;
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
