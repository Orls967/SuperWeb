@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Fleet" />

<div class="py-12 bg-slate-950 text-slate-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Manajemen Armada &amp; Corporate Leasing</h1>
                        <p class="text-sm text-slate-400">PSAK 73 Financial Amortization, Telematics SLA Tracking &amp; AutoDex Handoff</p>
                    </div>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                    PSAK 73 Compliant
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    SLA Live Telematics
                </span>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- 4 KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800/80 shadow-lg relative overflow-hidden">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Nilai Portofolio Leasing</div>
                <div class="mt-2 text-2xl font-black text-white">Rp {{ number_format($totalPortfolioIdr) }}</div>
                <div class="mt-1 text-xs text-indigo-400">Kontrak aktif korporat</div>
                <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-indigo-500/5 rounded-full blur-xl"></div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800/80 shadow-lg relative overflow-hidden">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Amortisasi PSAK 73 Terposting</div>
                <div class="mt-2 text-2xl font-black text-emerald-400">Rp {{ number_format($totalAmortizedIdr) }}</div>
                <div class="mt-1 text-xs text-slate-400">Pokok + Bunga seimbang ke Ledger</div>
                <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-emerald-500/5 rounded-full blur-xl"></div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800/80 shadow-lg relative overflow-hidden">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Uptime Armada Telematika</div>
                <div class="mt-2 text-2xl font-black text-amber-400">{{ $fleetUptimePct }}%</div>
                <div class="mt-1 text-xs text-slate-400">{{ $units->where('status', 'assigned')->count() }} unit aktif beroperasi</div>
                <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-amber-500/5 rounded-full blur-xl"></div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800/80 shadow-lg relative overflow-hidden">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Penalti SLA &amp; Kredit Nota</div>
                <div class="mt-2 text-2xl font-black text-rose-400">Rp {{ number_format($totalSlaPenaltiesIdr) }}</div>
                <div class="mt-1 text-xs text-slate-400">Pengurang tagihan AR penyewa</div>
                <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-rose-500/5 rounded-full blur-xl"></div>
            </div>
        </div>

        <!-- 4 Tabs Container (Alpine.js) -->
        <div x-data="{ tab: 'contracts' }" class="space-y-6">
            <div class="flex border-b border-slate-800 gap-2 overflow-x-auto">
                <button @click="tab = 'contracts'" :class="tab === 'contracts' ? 'border-indigo-500 text-indigo-400 bg-slate-900/80' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-xl transition whitespace-nowrap">
                    1. Portofolio Kontrak B2B
                </button>
                <button @click="tab = 'schedule'" :class="tab === 'schedule' ? 'border-indigo-500 text-indigo-400 bg-slate-900/80' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-xl transition whitespace-nowrap">
                    2. Jadwal Amortisasi PSAK 73
                </button>
                <button @click="tab = 'sla'" :class="tab === 'sla' ? 'border-indigo-500 text-indigo-400 bg-slate-900/80' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-xl transition whitespace-nowrap">
                    3. Monitoring SLA Telematika
                </button>
                <button @click="tab = 'lifecycle'" :class="tab === 'lifecycle' ? 'border-indigo-500 text-indigo-400 bg-slate-900/80' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-xl transition whitespace-nowrap">
                    4. Lifecycle &amp; AutoDex Handoff
                </button>
            </div>

            <!-- Tab 1: Contracts -->
            <div x-show="tab === 'contracts'" class="space-y-4">
                <div class="bg-slate-900/50 backdrop-blur-md rounded-2xl border border-slate-800 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Daftar Kontrak Leasing Korporat</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-800/60 text-slate-400 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3">No. Kontrak</th>
                                    <th class="px-4 py-3">Penyewa (Party)</th>
                                    <th class="px-4 py-3">Durasi</th>
                                    <th class="px-4 py-3">Unit</th>
                                    <th class="px-4 py-3">Sewa Bulanan</th>
                                    <th class="px-4 py-3">Total Kontrak</th>
                                    <th class="px-4 py-3">Teramortisasi</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse($contracts as $c)
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="px-4 py-3 font-mono font-medium text-white">{{ $c->contract_number }}</td>
                                        <td class="px-4 py-3">Party #{{ $c->party_id }}</td>
                                        <td class="px-4 py-3">{{ $c->duration_months }} Bulan</td>
                                        <td class="px-4 py-3">{{ $c->total_units }} Unit</td>
                                        <td class="px-4 py-3">Rp {{ number_format($c->monthly_rental_idr) }}</td>
                                        <td class="px-4 py-3 font-medium text-white">Rp {{ number_format($c->total_lease_value_idr) }}</td>
                                        <td class="px-4 py-3 text-emerald-400">Rp {{ number_format($c->accumulated_amortized_idr) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                {{ $c->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                                {{ $c->status === 'pending_approval' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                                {{ $c->status === 'completed' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : '' }}
                                                {{ $c->status === 'terminated' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                            ">
                                                {{ strtoupper($c->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($c->status === 'active')
                                                <form action="{{ route('fleet.contracts.amortize', $c) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="month_index" value="{{ min($c->duration_months, (int)($c->accumulated_amortized_idr / max(1, $c->monthly_rental_idr)) + 1) }}">
                                                    <button type="submit" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition">
                                                        Amortisasi
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">Belum ada kontrak sewa armada terdaftar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: PSAK 73 Schedule -->
            <div x-show="tab === 'schedule'" class="space-y-4">
                <div class="bg-slate-900/50 backdrop-blur-md rounded-2xl border border-slate-800 p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">Simulasi Jadwal Amortisasi PSAK 73 / IFRS 16</h3>
                    <p class="text-xs text-slate-400 mb-6">Pemisahan presisi beban bunga efektif ($i = 10\%$ p.a.) vs pokok liabilitas sewa.</p>
                    
                    @if($contracts->isNotEmpty())
                        @php
                            $sampleContract = $contracts->first();
                            $leasingService = app(\Modules\Mobility\Fleet\Application\Services\FleetLeasingService::class);
                            $sampleSchedule = $leasingService->generatePsak73Schedule($sampleContract);
                        @endphp
                        <div class="mb-4 p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-slate-400">Menampilkan Jadwal untuk Kontrak:</span>
                                <span class="text-sm font-mono font-bold text-white ml-2">{{ $sampleContract->contract_number }}</span>
                            </div>
                            <div class="text-xs text-indigo-400 font-medium">
                                Nilai PV: Rp {{ number_format($sampleContract->present_value_liability_idr) }}
                            </div>
                        </div>
                        <div class="overflow-x-auto max-h-96">
                            <table class="w-full text-left text-sm text-slate-300">
                                <thead class="bg-slate-800/80 text-slate-400 text-xs uppercase sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Bulan (t)</th>
                                        <th class="px-4 py-3">Pembayaran (PMT)</th>
                                        <th class="px-4 py-3">Pokok Liabilitas</th>
                                        <th class="px-4 py-3">Beban Bunga</th>
                                        <th class="px-4 py-3">Sisa Liabilitas (PV)</th>
                                        <th class="px-4 py-3">Kumulatif Amortisasi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800 font-mono text-xs">
                                    @foreach($sampleSchedule as $item)
                                        <tr class="hover:bg-slate-800/30">
                                            <td class="px-4 py-2 font-bold text-white">{{ $item['month'] }}</td>
                                            <td class="px-4 py-2">Rp {{ number_format($item['payment']) }}</td>
                                            <td class="px-4 py-2 text-emerald-400">Rp {{ number_format($item['principal']) }}</td>
                                            <td class="px-4 py-2 text-amber-400">Rp {{ number_format($item['interest']) }}</td>
                                            <td class="px-4 py-2 text-slate-400">Rp {{ number_format($item['remaining_liability']) }}</td>
                                            <td class="px-4 py-2 text-indigo-300">Rp {{ number_format($item['accumulated_amortized']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-slate-500">Tidak ada kontrak untuk disimulasikan.</div>
                    @endif
                </div>
            </div>

            <!-- Tab 3: SLA & Telematics -->
            <div x-show="tab === 'sla'" class="space-y-4">
                <div class="bg-slate-900/50 backdrop-blur-md rounded-2xl border border-slate-800 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Log Monitoring SLA &amp; Kompensasi Downtime Telematika</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-800/60 text-slate-400 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-3">Periode</th>
                                    <th class="px-4 py-3">Kontrak</th>
                                    <th class="px-4 py-3">Kendaraan</th>
                                    <th class="px-4 py-3">Telematika Grounded</th>
                                    <th class="px-4 py-3">Bengkel Servis</th>
                                    <th class="px-4 py-3">Total Downtime</th>
                                    <th class="px-4 py-3">Pelanggaran</th>
                                    <th class="px-4 py-3">Kredit Nota AR</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse($slaLogs as $l)
                                    <tr class="hover:bg-slate-800/30 transition font-mono text-xs">
                                        <td class="px-4 py-3 font-bold text-white">{{ $l->incident_month }}</td>
                                        <td class="px-4 py-3">{{ $l->contract?->contract_number }}</td>
                                        <td class="px-4 py-3">#{{ $l->vehicle_id }}</td>
                                        <td class="px-4 py-3">{{ $l->telematics_grounded_hours }} jam</td>
                                        <td class="px-4 py-3">{{ $l->workshop_service_hours }} jam</td>
                                        <td class="px-4 py-3 text-amber-400">{{ $l->total_downtime_hours }} jam</td>
                                        <td class="px-4 py-3 text-rose-400">{{ $l->breach_hours }} jam</td>
                                        <td class="px-4 py-3 text-emerald-400 font-bold">Rp {{ number_format($l->penalty_credit_idr) }}</td>
                                        <td class="px-4 py-3">
                                            @if($l->is_compensated)
                                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                    KREDIT POSTED
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-500/10 text-slate-400">
                                                    NORMAL
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">Belum ada catatan pelanggaran downtime SLA.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Lifecycle & AutoDex Handoff -->
            <div x-show="tab === 'lifecycle'" class="space-y-4">
                <div class="bg-slate-900/50 backdrop-blur-md rounded-2xl border border-slate-800 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Unit Armada &amp; Siklus Pengembalian (AutoDex Handoff)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse($units as $u)
                            <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="font-bold text-white">Kendaraan #{{ $u->vehicle_id }}</div>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium 
                                        {{ $u->status === 'assigned' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                        {{ $u->status === 'returned' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : '' }}
                                        {{ $u->status === 'replaced' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                    ">
                                        {{ strtoupper($u->status) }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-400 space-y-1">
                                    <div>Kontrak: <span class="text-slate-200 font-mono">{{ $u->contract?->contract_number }}</span></div>
                                    <div>Odometer Berjalan: <span class="text-white">{{ number_format($u->current_odometer_km) }} km</span> / Plafon: {{ number_format($u->max_km_per_year) }} km</div>
                                    <div>Downtime Tercatat: <span class="text-amber-400">{{ $u->downtime_hours_recorded }} jam</span></div>
                                    @if($u->condition_report_notes)
                                        <div class="mt-2 p-2 bg-slate-900/80 rounded border border-slate-800 text-slate-300">
                                            Catatan: {{ $u->condition_report_notes }}
                                        </div>
                                    @endif
                                </div>
                                @if($u->status === 'assigned' && $u->contract)
                                    <form action="{{ route('fleet.contracts.return-unit', $u->contract) }}" method="POST" class="pt-2">
                                        @csrf
                                        <input type="hidden" name="vehicle_id" value="{{ $u->vehicle_id }}">
                                        <input type="hidden" name="condition_notes" value="End of lease condition inspection passed - Ready for AutoDex inventory.">
                                        <button type="submit" class="w-full py-1.5 px-3 bg-slate-700 hover:bg-slate-600 text-white text-xs font-semibold rounded-lg transition">
                                            Kembalikan ke AutoDex
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-8 text-slate-500">Belum ada unit terpasang pada armada.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
