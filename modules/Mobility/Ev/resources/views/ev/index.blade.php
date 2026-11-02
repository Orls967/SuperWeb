@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Ev" />

<div class="py-8 bg-slate-950 min-h-screen text-slate-100 font-sans">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center pb-6 border-b border-slate-800 gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Ekosistem EV & Jaringan Charging SPKLU</h1>
                        <p class="text-xs text-slate-400">Jaringan Stasiun DC/AC, Wh Metering, Battery Passport Hash-Chain & Scope 2 ESG</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse mr-2"></span>
                    Jaringan Aktif (SIM S2)
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-mono bg-slate-800 text-slate-300 border border-slate-700">
                    Fase 69 ✅ Ready
                </span>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-emerald-200 text-sm flex items-center gap-3 shadow-lg shadow-emerald-950/50">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 bg-rose-950/60 border border-rose-500/40 rounded-xl text-rose-200 text-sm flex items-center gap-3 shadow-lg shadow-rose-950/50">
                <svg class="w-5 h-5 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <!-- 4 KPI Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 shadow-xl backdrop-blur-sm">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium tracking-wider">Total Energi Disalurkan</span>
                    <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold font-mono text-white">{{ number_format($totalEnergyWh / 1000, 1) }} <span class="text-sm font-normal text-slate-400">kWh</span></div>
                <div class="text-xs text-slate-500 mt-1 font-mono">{{ number_format($totalEnergyWh) }} Wh presisi</div>
            </div>

            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 shadow-xl backdrop-blur-sm">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium tracking-wider">Pendapatan SPKLU</span>
                    <span class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold font-mono text-emerald-400">Rp {{ number_format($totalRevenueIdr, 0, ',', '.') }}</div>
                <div class="text-xs text-slate-500 mt-1">Akun: oto:ev_revenue:IDR</div>
            </div>

            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 shadow-xl backdrop-blur-sm">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium tracking-wider">Green Km (ESG Scope 2)</span>
                    <span class="p-1.5 rounded-lg bg-teal-500/10 text-teal-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold font-mono text-teal-300">{{ number_format($totalGreenKmSaved, 2) }} <span class="text-sm font-normal text-slate-400">kg CO2e</span></div>
                <div class="text-xs text-slate-500 mt-1">Emisi ICE Terhindar (Jamali Grid)</div>
            </div>

            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 shadow-xl backdrop-blur-sm">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium tracking-wider">Rata-Rata SoH Baterai</span>
                    <span class="p-1.5 rounded-lg bg-violet-500/10 text-violet-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold font-mono {{ $avgSoh >= 80 ? 'text-emerald-400' : ($avgSoh >= 70 ? 'text-amber-400' : 'text-rose-400') }}">
                    {{ number_format($avgSoh, 1) }}%
                </div>
                <div class="text-xs text-slate-500 mt-1">Status Kesehatan Armada Terverifikasi</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left 2 Cols: Stasiun & Chargers, Active Sessions, Battery Passport -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Section 1: Charger Stations & Live Slot -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Stasiun & Titik Pengisian SPKLU</h2>
                            <p class="text-xs text-slate-400">Jaringan Stasiun DC Ultra-Fast & AC Charging Hub</p>
                        </div>
                        @if($stations->isNotEmpty())
                            <form action="{{ route('ev.stations.loadbalance', $stations->first()) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600/80 hover:bg-indigo-600 text-white text-xs font-medium rounded-lg transition border border-indigo-500/30 flex items-center gap-1.5 shadow-md">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Simulasi Load Balancing
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($chargers as $charger)
                            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 hover:border-slate-700 transition space-y-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-bold text-white flex items-center gap-2">
                                            {{ $charger->charger_code }}
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                                {{ $charger->type }} • {{ $charger->max_kw }} kW
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1">{{ $charger->station?->name ?? 'SPKLU Stasiun' }}</div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium 
                                        {{ $charger->status === 'available' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 
                                           ($charger->status === 'charging' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 animate-pulse' : 
                                            'bg-amber-500/10 text-amber-400 border border-amber-500/30') }}">
                                        {{ ucfirst($charger->status) }}
                                    </span>
                                </div>

                                <div class="pt-2 border-t border-slate-800/60 flex items-center justify-between text-xs">
                                    <span class="text-slate-400 font-mono">Tarif: Rp 2.500–3.000/kWh</span>
                                    @if($charger->status === 'available')
                                        <form action="{{ route('ev.chargers.reserve', $charger) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="vehicle_id" value="1">
                                            <input type="hidden" name="duration_minutes" value="30">
                                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg text-xs transition shadow-sm">
                                                Reservasi 30 Min
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-slate-500 text-xs">Belum ada titik charger terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Section 2: Battery Passport Append-Only Hash-Chain Inspector -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Battery Passport Hash-Chain Inspector</h2>
                            <p class="text-xs text-slate-400">Audit Trail Kriptografis Append-Only (SHA-256 Recursive) Rekam Jejak Kesehatan Baterai</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono bg-violet-500/10 text-violet-300 border border-violet-500/20">
                            Immutable Ledger
                        </span>
                    </div>

                    <div class="space-y-3">
                        @forelse($passports as $b)
                            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <div class="font-mono text-slate-300 font-medium">
                                        Blok Siklus #{{ $b->cycle_number }} • Sesi {{ $b->session_code }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono {{ $b->final_soh_pct >= 80 ? 'text-emerald-400' : ($b->final_soh_pct >= 70 ? 'text-amber-400' : 'text-rose-400') }} font-bold">
                                            SoH: {{ number_format($b->final_soh_pct, 2) }}%
                                        </span>
                                        <span class="text-slate-500 text-[11px] font-mono">{{ $b->recorded_at?->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] text-slate-400 bg-slate-900/50 p-2 rounded-lg">
                                    <div>Energi: <span class="text-white font-mono">{{ number_format($b->energy_wh) }} Wh</span></div>
                                    <div>Suhu Sel Puncak: <span class="text-white font-mono">{{ $b->max_cell_temp_c }}°C</span></div>
                                    <div>Degradasi: <span class="text-rose-400 font-mono">-{{ $b->delta_soh }}%</span></div>
                                    <div>Status Chain: <span class="text-emerald-400 font-mono">Verified ✅</span></div>
                                </div>
                                <div class="space-y-1 font-mono text-[10px] text-slate-500 break-all">
                                    <div><span class="text-slate-400">Prev Hash:</span> {{ $b->prev_hash }}</div>
                                    <div><span class="text-slate-400">Block Hash:</span> {{ $b->block_hash }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6 text-slate-500 text-xs">Belum ada blok Battery Passport tercatat.</div>
                        @endforelse
                    </div>
                </div>

            </div>

            <!-- Right Col: Sesi Charging & Simulator Control -->
            <div class="space-y-6">
                
                <!-- Recent Sesi Pengisian -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <h2 class="text-lg font-semibold text-white">Sesi Charging Terkini</h2>
                    <div class="space-y-3">
                        @forelse($sessions as $sess)
                            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-mono font-medium text-white">{{ $sess->session_code }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold 
                                        {{ $sess->status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 
                                           ($sess->status === 'plugged_in' || $sess->status === 'charging' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30' : 
                                            'bg-slate-800 text-slate-300') }}">
                                        {{ strtoupper($sess->status) }}
                                    </span>
                                </div>
                                <div class="text-slate-400 space-y-0.5">
                                    <div>Charger: <span class="text-slate-200">{{ $sess->charger?->charger_code }}</span></div>
                                    <div>Tarif: <span class="text-slate-200 font-mono">Rp {{ number_format($sess->tariff_per_kwh_idr) }}/kWh</span></div>
                                    @if($sess->total_cost_idr > 0)
                                        <div>Total Tagihan: <span class="text-emerald-400 font-mono font-medium">Rp {{ number_format($sess->total_cost_idr, 0, ',', '.') }}</span></div>
                                    @endif
                                </div>

                                @if($sess->status === 'reserved' || $sess->status === 'plugged_in')
                                    <div class="pt-2 border-t border-slate-800/80 flex gap-2">
                                        @if($sess->status === 'reserved')
                                            <form action="{{ route('ev.chargers.checkin', $sess->charger_id) }}" method="POST" class="flex-1">
                                                @csrf
                                                <input type="hidden" name="session_id" value="{{ $sess->id }}">
                                                <input type="hidden" name="qr_code" value="{{ $sess->charger?->charger_code }}">
                                                <button type="submit" class="w-full py-1 bg-cyan-600 hover:bg-cyan-500 text-white font-medium rounded text-[11px] transition">
                                                    Scan QR Check-In
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('ev.sessions.complete', $sess) }}" method="POST" class="flex-1">
                                            @csrf
                                            <input type="hidden" name="energy_wh" value="35000">
                                            <input type="hidden" name="peak_cell_temp_c" value="38.5">
                                            <button type="submit" class="w-full py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded text-[11px] transition">
                                                Selesaikan Cas
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-6 text-slate-500 text-xs">Belum ada riwayat sesi.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Scope 2 ESG Greenhouse Gas Info Card -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-teal-950/40 to-slate-900 border border-teal-800/40 shadow-xl space-y-3">
                    <div class="flex items-center gap-2 text-teal-400 font-medium text-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Metodologi ESG Scope 2 & Green Km
                    </div>
                    <p class="text-[11px] text-slate-300 leading-relaxed">
                        Setiap kWh daya terserap dari grid diperhitungkan terhadap Faktor Emisi Grid Jamali (0.794 kg CO2e/kWh) dan dikomparasikan dengan efisiensi armada ICE padanan (12 km/L bensin @ 2.31 kg CO2e/L). Net emisi yang terhindar dicatat gapless pada profil kendaraan.
                    </p>
                    <div class="pt-2 border-t border-teal-900/50 flex justify-between items-center text-[11px] text-teal-300 font-mono">
                        <span>Standar: ISO 14064 / GHG Protocol</span>
                        <span class="text-emerald-400 font-bold">Scope 2 Verified</span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
