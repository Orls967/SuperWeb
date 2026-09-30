<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Registrasi Kontrak Sewa Unit Baru
                </h2>
                <p class="text-sm text-slate-500 mt-1">Buat draft perjanjian leasing komersial dengan tenant mitra</p>
            </div>
            <div>
                <a href="{{ route('mall.leases.index') }}" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50">
                    &larr; Kembali
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
                <form method="POST" action="{{ route('mall.leases.store') }}" class="space-y-6" x-data="{
                    units: {{ json_encode($units->map(fn($u) => [
                        'id' => $u->id,
                        'property_id' => $u->property_id,
                        'number' => $u->unit_number,
                        'floor' => $u->floor,
                        'area' => $u->area_sqm,
                        'base_rate' => $u->base_rent_rate_per_sqm,
                        'sc_rate' => $u->service_charge_per_sqm,
                        'est_base' => $u->estimatedBaseRent(),
                        'est_sc' => $u->estimatedServiceCharge()
                    ])) }},
                    selectedUnitId: '{{ request('unit_id', '') }}',
                    selectedPropertyId: '{{ $properties->first()?->id }}',
                    get currentUnit() {
                        return this.units.find(u => u.id == this.selectedUnitId);
                    }
                }">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Properti Mall *</label>
                            <select name="property_id" x-model="selectedPropertyId" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->name }} ({{ $prop->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Unit Ruang Komersial *</label>
                            <select name="unit_id" x-model="selectedUnitId" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Pilih Unit --</option>
                                <template x-for="u in units.filter(u => u.property_id == selectedPropertyId)" :key="u.id">
                                    <option :value="u.id" x-text="'Unit ' + u.number + ' (Lt. ' + u.floor + ', ' + u.area + ' m²)'"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Unit Estimation Hint Box -->
                    <template x-if="currentUnit">
                        <div class="p-4 rounded-xl bg-blue-50/60 border border-blue-100 flex flex-wrap items-center justify-between gap-3 text-xs text-blue-900">
                            <div>
                                <span class="font-bold">Estimasi Sewa Dasar Standar:</span>
                                <span class="font-mono font-black ml-1" x-text="'Rp ' + Number(currentUnit.est_base).toLocaleString('id-ID') + ' /bulan'"></span>
                            </div>
                            <div>
                                <span class="font-bold">Service Charge Standar:</span>
                                <span class="font-mono font-black ml-1" x-text="'Rp ' + Number(currentUnit.est_sc).toLocaleString('id-ID') + ' /bulan'"></span>
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tenant Mitra Penyewa *</label>
                        <select name="tenant_id" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Pilih Tenant --</option>
                            @foreach($tenants as $t)
                                <option value="{{ $t->id }}">{{ $t->brand_name }} ({{ $t->company_name }}) - {{ $t->category->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Model Sewa (Rent Model) *</label>
                            <select name="rent_model" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                                @foreach($rentModels as $rm)
                                    <option value="{{ $rm->value }}">{{ $rm->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Bagi Hasil Omzet (%) (Jika Rev Share)</label>
                            <input type="number" step="0.1" min="0" max="50" name="revenue_share_percent" value="10.0" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="contoh: 10.0">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Mulai Sewa *</label>
                            <input type="date" name="start_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Berakhir Sewa *</label>
                            <input type="date" name="end_date" value="{{ date('Y-m-d', strtotime('+1 year')) }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Masa Fit-Out (Hari Bebas Sewa)</label>
                            <input type="number" name="fit_out_days" value="30" min="0" max="180" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Sewa Bulanan Kustom (IDR) (Kosongkan utk standar unit)</label>
                            <input type="number" name="base_monthly_rent" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="Otomatis dari luas x tarif">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Service Charge Kustom (IDR) (Kosongkan utk standar unit)</label>
                            <input type="number" name="service_charge_monthly" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="Otomatis dari luas x SC">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Eskalasi Tarif Tahunan (%) *</label>
                            <input type="number" step="0.1" min="0" max="50" name="annual_escalation_percent" value="5.0" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Deposit Jaminan Sewa (Bulan) *</label>
                            <input type="number" min="1" max="12" name="security_deposit_months" value="3" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <a href="{{ route('mall.leases.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                            Batal
                        </a>
                        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all">
                            Simpan Draft Kontrak Sewa
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
