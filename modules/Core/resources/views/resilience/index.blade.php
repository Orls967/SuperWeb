@extends('layouts.app')

@section('title', 'Resiliensi Global, DR Multi-Region & Data Sovereignty')
@section('subtitle', 'Tata Kelola BCP, Chaos Engineering Failover, Rotasi Kunci KMS & Kepatuhan Residensi Data (Fase 66)')

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'overview' }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    @if(session('residency_result'))
        @php $res = session('residency_result'); @endphp
        <div class="p-4 rounded-2xl {{ $res['allowed'] ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400' }} border text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <span class="font-semibold">{{ $res['allowed'] ? 'Kedaulatan Data Memenuhi Regulasi' : 'Pelanggaran Kedaulatan Data Terdeteksi' }}</span>
                    <p class="text-xs opacity-90 mt-0.5">{{ $res['allowed'] ? 'Data tersimpan sesuai ketentuan ' . $res['regulation'] : $res['violation'] }}</p>
                </div>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold hover:opacity-75">&times;</button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Primary Region</p>
                <p class="text-xl font-bold text-slate-100">{{ $topology['primary']['region'] }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">{{ $topology['primary']['location'] }} (Active)</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">DR Standby Region</p>
                <p class="text-xl font-bold text-slate-100">{{ $topology['dr_standby']['region'] }}</p>
                <p class="text-xs text-cyan-400 mt-0.5">Lag: {{ $topology['dr_standby']['lag_seconds'] }}s (Read Replica)</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Target SLA Terukur</p>
                <p class="text-xl font-bold text-slate-100">RPO &le; 1m / RTO &le; 15m</p>
                <p class="text-xs text-emerald-400 mt-0.5">Multi-Region Hot Standby</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">KMS Active Rotations</p>
                <p class="text-xl font-bold text-slate-100">{{ $kmsKeys->where('status', 'ACTIVE')->count() }} Kunci Aktif</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $drills->count() }} Simulasi DR Selesai</p>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="border-b border-slate-700/60 flex gap-2">
        <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Topologi & Kedaulatan
        </button>
        <button type="button" @click="activeTab = 'drills'" :class="activeTab === 'drills' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Simulasi DR & Chaos Drill
        </button>
        <button type="button" @click="activeTab = 'kms'" :class="activeTab === 'kms' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Enterprise KMS & Envelope Encryption
        </button>
        <button type="button" @click="activeTab = 'sovereignty'" :class="activeTab === 'sovereignty' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Evaluasi UU PDP & Data Residency
        </button>
    </div>

    {{-- TAB 1: Topologi & Kedaulatan --}}
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Arsitektur Active-Passive & Global Read-Replica
                </h3>
                <div class="space-y-4 text-sm text-slate-300">
                    <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700/40 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs uppercase font-bold text-slate-400">Primary Region (Write Master)</span>
                            <span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300 font-semibold">PRIMARY</span>
                        </div>
                        <p class="font-medium text-slate-100">{{ $topology['primary']['region'] }} &mdash; {{ $topology['primary']['location'] }}</p>
                        <p class="text-xs text-slate-400">Replikasi: {{ $topology['primary']['replication_mode'] }} (Synchronous Wal Stream)</p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700/40 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs uppercase font-bold text-slate-400">DR Standby Region (Global Read-Replica)</span>
                            <span class="px-2 py-0.5 rounded text-xs bg-cyan-500/20 text-cyan-300 font-semibold">STANDBY</span>
                        </div>
                        <p class="font-medium text-slate-100">{{ $topology['dr_standby']['region'] }} &mdash; {{ $topology['dr_standby']['location'] }}</p>
                        <p class="text-xs text-slate-400">Lag Terukur: {{ $topology['dr_standby']['lag_seconds'] }}s &bull; Sinkronisasi Divergensi: 0 (Zero Loss)</p>
                    </div>
                </div>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Kebijakan Kedaulatan Data Aktif
                </h3>
                <div class="space-y-3">
                    @forelse($policies as $policy)
                        <div class="p-3 rounded-xl bg-slate-900/50 border border-slate-700/30 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">{{ $policy->jurisdiction_code }} &bull; {{ $policy->regulation_reference }}</span>
                                <p class="text-sm text-slate-200 font-medium">Residensi Wajib: {{ $policy->mandatory_storage_region }}</p>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-400 font-semibold">{{ $policy->status }}</span>
                                <p class="text-xs text-slate-400 mt-0.5">Enkripsi PII: {{ $policy->pii_encryption_required ? 'Wajib' : 'Opsional' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Belum ada kebijakan spesifik yang terkonfigurasi di basis data.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: Simulasi DR & Chaos Drill --}}
    <div x-show="activeTab === 'drills'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Jalankan Chaos Engineering & Failover Drill Deterministik
            </h3>
            <form action="{{ route('admin.resilience.execute-drill') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Tipe Drill Failover</label>
                    <select name="drill_type" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        <option value="FAILOVER_SIMULATION">Simulasi Failover Total</option>
                        <option value="READ_REPLICA_PROMOTION">Promosi Read Replica ke Primary</option>
                        <option value="DATA_CORRUPTION_RECOVERY">Pemulihan Korupsi Data Snapshot</option>
                        <option value="CHAOS_NETWORK_PARTITION">Chaos Network Partition</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Primary Region</label>
                    <input type="text" name="primary_region" value="ap-southeast-3" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Target DR Region</label>
                    <input type="text" name="target_dr_region" value="ap-southeast-1" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200" required>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Eksekusi Simulasi DR
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-200 mb-4">Riwayat Eksekusi Drill Terakhir</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700/50">
                        <tr>
                            <th class="px-4 py-3">UUID Drill</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">RPO / RTO</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Divergensi</th>
                            <th class="px-4 py-3">Checksum Hash</th>
                            <th class="px-4 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($drills as $drill)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-mono text-xs text-indigo-400">{{ $drill->drill_uuid }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $drill->drill_type }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-emerald-400 font-bold">{{ $drill->rpo_seconds }}s</span> / 
                                    <span class="text-cyan-400 font-bold">{{ $drill->rto_seconds }}s</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $drill->status === 'SUCCESS' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' }} font-bold">
                                        {{ $drill->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-bold {{ $drill->divergence_count === 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $drill->divergence_count }} Data Loss
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-400" title="{{ $drill->checksum_hash }}">
                                    {{ Str::limit($drill->checksum_hash, 16) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $drill->executed_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada drill simulasi yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 3: KMS & Envelope Encryption --}}
    <div x-show="activeTab === 'kms'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                Rotasi Kunci KMS Otomatis (Envelope Encryption)
            </h3>
            <form action="{{ route('admin.resilience.rotate-kms') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Key Alias</label>
                    <input type="text" name="key_alias" value="master-database-envelope-key" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Algoritma Enkripsi</label>
                    <select name="encryption_algorithm" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        <option value="AES-256-GCM">AES-256-GCM (Authenticated)</option>
                        <option value="CHACHA20-POLY1305">ChaCha20-Poly1305</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Alasan Rotasi</label>
                    <input type="text" name="rotation_reason" placeholder="Rotasi berkala triwulanan" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Rotasi Kunci KMS
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-200 mb-4">Siklus Rotasi Kunci KMS & Sidik Jari Kriptografis</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700/50">
                        <tr>
                            <th class="px-4 py-3">Key Alias</th>
                            <th class="px-4 py-3">Versi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Algoritma</th>
                            <th class="px-4 py-3">Fingerprint SHA-256</th>
                            <th class="px-4 py-3">Alasan Rotasi</th>
                            <th class="px-4 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($kmsKeys as $key)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-semibold text-slate-200">{{ $key->key_alias }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-amber-400">v{{ $key->key_version }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $key->status === 'ACTIVE' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-500/20 text-slate-400' }} font-bold">
                                        {{ $key->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $key->encryption_algorithm }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-cyan-400" title="{{ $key->key_fingerprint }}">
                                    {{ Str::limit($key->key_fingerprint, 20) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $key->rotation_reason }}</td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $key->rotated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada rotasi kunci KMS yang tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 4: Evaluasi UU PDP & Data Residency --}}
    <div x-show="activeTab === 'sovereignty'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Uji Kepatuhan Residensi Data (UU PDP No. 27/2022)
            </h3>
            <form action="{{ route('admin.resilience.check-residency') }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Yurisdiksi</label>
                    <select name="jurisdiction_code" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        <option value="ID">Indonesia (UU PDP)</option>
                        <option value="SG">Singapura (PDPA)</option>
                        <option value="GLOBAL">Global / Internasional</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Klasifikasi Data</label>
                    <select name="data_type" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        <option value="CUSTOMER_FINANCIAL_PROFILE">Profil Finansial Pelanggan</option>
                        <option value="TRANSACTION_LEDGER">Buku Besar Transaksi</option>
                        <option value="ANALYTICS_ANONYMIZED">Analitik Teranonim</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Region Penyimpanan</label>
                    <input type="text" name="storage_region" value="ap-southeast-3" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200" required>
                </div>
                <div class="flex flex-col justify-center space-y-2 pt-2">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" name="contains_pii" value="1" checked class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
                        <span>Memuat Data Pribadi (PII)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" name="is_cross_border_transfer" value="1" class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
                        <span>Transfer Lintas Batas</span>
                    </label>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 px-4 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                        Evaluasi Kepatuhan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
