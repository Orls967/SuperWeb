@extends('layouts.app')

@section('title', 'Simulation Kernel, Universal Event Spine & Digital Twin Bus')
@section('subtitle', 'Tata Kelola Waktu Deterministik, Bus Event Lintas 12 Lini & State Hash-Chain Digital Twin (Fase 67)')

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'overview' }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div id="sim-alert-success" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Virtual Clock</p>
                <p class="text-lg font-bold text-slate-100">{{ $virtualNow->format('Y-m-d H:i:s') }}</p>
                <p class="text-xs {{ $isSimulating ? 'text-amber-400' : 'text-slate-400' }} mt-0.5">
                    {{ $isSimulating ? 'Status: Active Simulating' : 'Status: Baseline Realtime' }}
                </p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Simulation Runs</p>
                <p class="text-xl font-bold text-slate-100">{{ number_format($stats['total_runs']) }}</p>
                <p class="text-xs text-blue-400 mt-0.5">Active Run: {{ $activeRun ? $activeRun->name : 'None' }}</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Event Spine Ingested</p>
                <p class="text-xl font-bold text-slate-100">{{ number_format($stats['total_spine_events']) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">12 Lini Bisnis Enabler</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Digital Twin States</p>
                <p class="text-xl font-bold text-slate-100">{{ number_format($stats['total_twin_states']) }}</p>
                <p class="text-xs text-amber-400 mt-0.5">100% Chain Verifiable</p>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="flex border-b border-slate-700/70 gap-6">
        <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-violet-500 text-violet-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Kernel Simulasi & Advance
        </button>
        <button type="button" @click="activeTab = 'spine'" :class="activeTab === 'spine' ? 'border-emerald-500 text-emerald-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Universal Event Spine
        </button>
        <button type="button" @click="activeTab = 'twin'" :class="activeTab === 'twin' ? 'border-amber-500 text-amber-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Digital Twin Bus
        </button>
        <button type="button" @click="activeTab = 'scale'" :class="activeTab === 'scale' ? 'border-cyan-500 text-cyan-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z"/></svg>
            Scale Provisioner Checkpoints
        </button>
    </div>

    {{-- Tab 1: Overview & Advance Clock --}}
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Advance --}}
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Akselerasi Jam Virtual Simulasi
                </h3>
                <p class="text-xs text-slate-400 mt-1 mb-4">Majukan waktu virtual terkompresi dan eksekusi seluruh daily tick handler (depresiasi, jatuh tempo, expiry, MRP).</p>

                <form action="{{ route('admin.simulation.advance') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Nama Sesi Simulasi</label>
                        <input type="text" name="run_name" value="{{ $activeRun ? $activeRun->name : 'sim_run_' . now()->format('Ymd') }}" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-violet-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Jumlah Hari Akselerasi (1–365)</label>
                        <input type="number" name="days" value="1" min="1" max="365" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-violet-500">
                    </div>
                    <button type="submit" id="btn-advance-simulation" class="w-full py-2.5 px-4 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-medium text-sm transition-all shadow-lg shadow-violet-500/20">
                        Eksekusi Akselerasi Hari
                    </button>
                </form>
            </div>

            {{-- Histori Run Simulasi --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                    <span>Histori Sesi Simulation Kernel</span>
                    <span class="text-xs text-slate-400 font-normal">{{ $recentRuns->count() }} sesi tercatat</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-700/70 text-slate-400">
                                <th class="pb-2">Nama Sesi</th>
                                <th class="pb-2">Waktu Virtual</th>
                                <th class="pb-2">Hari</th>
                                <th class="pb-2">Seed</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2">State Hash</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40 text-slate-300">
                            @forelse($recentRuns as $run)
                                <tr class="hover:bg-slate-700/20">
                                    <td class="py-2.5 font-medium text-slate-100">{{ $run->name }}</td>
                                    <td class="py-2.5 font-mono text-slate-300">{{ $run->virtual_now }}</td>
                                    <td class="py-2.5 font-semibold text-violet-400">Hari ke-{{ $run->last_completed_day }}</td>
                                    <td class="py-2.5 font-mono">{{ $run->seed }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $run->status === 'running' ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-700 text-slate-300' }}">
                                            {{ strtoupper($run->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 font-mono text-[10px] text-slate-400">
                                        {{ $run->last_state_hash ? substr($run->last_state_hash, 0, 16) . '...' : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-400">Belum ada sesi simulasi tercatat. Silakan jalankan akselerasi pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab 2: Universal Event Spine --}}
    <div x-show="activeTab === 'spine'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Publish Event Spine --}}
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Terbitkan Event Spine Baru
                </h3>
                <p class="text-xs text-slate-400 mt-1 mb-4">Kirim domain event ber-idempotency key ke topik spine 12 lini bisnis.</p>

                <form action="{{ route('admin.simulation.publish-spine') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Topik Domain (12 Lini)</label>
                        <select name="topic" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-emerald-500">
                            <option value="auto.vehicle_acquired">auto.vehicle_acquired</option>
                            <option value="fintech.loan_approved">fintech.loan_approved</option>
                            <option value="resto.order_completed">resto.order_completed</option>
                            <option value="proptech.building_alert">proptech.building_alert</option>
                            <option value="lgx.shipment_delivered">lgx.shipment_delivered</option>
                            <option value="mfg.batch_released">mfg.batch_released</option>
                            <option value="trade.invoice_settled">trade.invoice_settled</option>
                            <option value="gov.compliance_verified">gov.compliance_verified</option>
                            <option value="hsp.patient_discharged">hsp.patient_discharged</option>
                            <option value="ven.ticket_sold">ven.ticket_sold</option>
                            <option value="htl.room_checked_in">htl.room_checked_in</option>
                            <option value="min.ore_extracted">min.ore_extracted</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Event Name</label>
                        <input type="text" name="event_name" value="VehicleAnomalyDetected" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Payload JSON</label>
                        <textarea name="payload" rows="3" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 font-mono text-xs focus:outline-none focus:border-emerald-500">{"unit_id": "VH-991", "severity": "critical", "source": "telematics_spine"}</textarea>
                    </div>
                    <button type="submit" id="btn-publish-spine" class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm transition-all shadow-lg shadow-emerald-500/20">
                        Publish ke Event Spine
                    </button>
                </form>
            </div>

            {{-- Log Spine Ingested --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                    <span>Universal Event Spine Log (Live Offset Stream)</span>
                    <span class="text-xs text-slate-400 font-normal">{{ $spineEvents->count() }} event termutakhir</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-700/70 text-slate-400">
                                <th class="pb-2">Offset</th>
                                <th class="pb-2">Topik</th>
                                <th class="pb-2">Event</th>
                                <th class="pb-2">Waktu Terbit</th>
                                <th class="pb-2">Idempotency Key</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40 text-slate-300">
                            @forelse($spineEvents as $event)
                                <tr class="hover:bg-slate-700/20">
                                    <td class="py-2.5 font-mono text-emerald-400 font-semibold">#{{ $event->id }}</td>
                                    <td class="py-2.5"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-300 font-mono text-[11px]">{{ $event->topic }}</span></td>
                                    <td class="py-2.5 font-medium text-slate-100">{{ $event->event_name }}</td>
                                    <td class="py-2.5 font-mono text-slate-400">{{ $event->occurred_at }}</td>
                                    <td class="py-2.5 font-mono text-[10px] text-slate-400">{{ substr($event->idempotency_key, 0, 20) }}...</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">Belum ada domain event yang dipublikasikan ke spine.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab 3: Digital Twin Bus --}}
    <div x-show="activeTab === 'twin'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Record Twin --}}
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Catat State Digital Twin
                </h3>
                <p class="text-xs text-slate-400 mt-1 mb-4">Perbarui state entitas bernilai tinggi dengan hash rantai SHA-256 berkait.</p>

                <form action="{{ route('admin.simulation.record-twin') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Entity Type</label>
                        <select name="entity_type" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-amber-500">
                            <option value="vehicle">vehicle (Kendaraan)</option>
                            <option value="building">building (Gedung Smart)</option>
                            <option value="container">container (Kontainer Logistik)</option>
                            <option value="mining_rig">mining_rig (Alat Berat Tambang)</option>
                            <option value="hotel_room">hotel_room (Kamar Hotel)</option>
                            <option value="patient_episode">patient_episode (Episode Pasien RS)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Entity ID</label>
                        <input type="text" name="entity_id" value="TWIN-VEH-1001" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">State JSON</label>
                        <textarea name="state" rows="3" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 font-mono text-xs focus:outline-none focus:border-amber-500">{"odometer_km": 14200, "battery_soh": 94.5, "status": "operational"}</textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_sandbox" value="1" id="is_sandbox" class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-0">
                        <label for="is_sandbox" class="text-xs text-slate-300">Sandbox Mode (What-if simulation)</label>
                    </div>
                    <button type="submit" id="btn-record-twin" class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium text-sm transition-all shadow-lg shadow-amber-500/20">
                        Simpan State & Generate Hash
                    </button>
                </form>
            </div>

            {{-- Log Digital Twin State Hash-Chain --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                    <span>Digital Twin Hash-Chain Ledger</span>
                    <span class="text-xs text-slate-400 font-normal">{{ $twinStates->count() }} rekaman termutakhir</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-700/70 text-slate-400">
                                <th class="pb-2">Entitas</th>
                                <th class="pb-2">State Hash (SHA-256)</th>
                                <th class="pb-2">Prev Hash</th>
                                <th class="pb-2">Sandbox</th>
                                <th class="pb-2">Valid From</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40 text-slate-300">
                            @forelse($twinStates as $twin)
                                <tr class="hover:bg-slate-700/20">
                                    <td class="py-2.5 font-medium text-slate-100">
                                        <span class="text-amber-400 font-mono">{{ $twin->entity_type }}</span>:{{ $twin->entity_id }}
                                    </td>
                                    <td class="py-2.5 font-mono text-[10px] text-emerald-400">{{ substr($twin->state_hash, 0, 18) }}...</td>
                                    <td class="py-2.5 font-mono text-[10px] text-slate-400">{{ substr($twin->prev_hash, 0, 18) }}...</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] {{ $twin->is_sandbox ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-700 text-slate-300' }}">
                                            {{ $twin->is_sandbox ? 'SANDBOX' : 'PRODUCTION' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 font-mono text-slate-400">{{ $twin->valid_from }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">Belum ada state digital twin yang tersimpan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab 4: Scale Provisioner Checkpoints --}}
    <div x-show="activeTab === 'scale'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Checkpoint Seeder --}}
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Simpan Checkpoint Seeder
                </h3>
                <p class="text-xs text-slate-400 mt-1 mb-4">Atur status etape seeder skala jutaan baris (Fictional Scale Provisioner).</p>

                <form action="{{ route('admin.simulation.scale-provision') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Seeder Name</label>
                        <input type="text" name="seeder_name" value="EnterpriseUniverseSeeder" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Stage Name</label>
                        <input type="text" name="stage" value="telematics_ticks_stage_1" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Batch Number</label>
                        <input type="number" name="batch_number" value="10" min="1" required class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-cyan-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="completed" value="1" id="completed_stage" checked class="rounded bg-slate-900 border-slate-700 text-cyan-500 focus:ring-0">
                        <label for="completed_stage" class="text-xs text-slate-300">Tandai Stage Selesai (Completed)</label>
                    </div>
                    <button type="submit" id="btn-save-checkpoint" class="w-full py-2.5 px-4 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-medium text-sm transition-all shadow-lg shadow-cyan-500/20">
                        Perbarui Checkpoint Etape
                    </button>
                </form>
            </div>

            {{-- Table Checkpoints --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                    <span>Daftar Checkpoint Seeder Skala</span>
                    <span class="text-xs text-slate-400 font-normal">{{ $checkpoints->count() }} etape tersimpan</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-700/70 text-slate-400">
                                <th class="pb-2">Seeder</th>
                                <th class="pb-2">Stage</th>
                                <th class="pb-2">Batch</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2">Terakhir Diperbarui</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40 text-slate-300">
                            @forelse($checkpoints as $cp)
                                <tr class="hover:bg-slate-700/20">
                                    <td class="py-2.5 font-medium text-slate-100">{{ $cp->seeder_name }}</td>
                                    <td class="py-2.5 font-mono text-cyan-400">{{ $cp->stage }}</td>
                                    <td class="py-2.5 font-semibold text-slate-300">Batch #{{ $cp->batch_number }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $cp->completed ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' }}">
                                            {{ $cp->completed ? 'COMPLETED' : 'IN_PROGRESS' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 font-mono text-slate-400">{{ $cp->updated_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">Belum ada checkpoint seeder skala tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
