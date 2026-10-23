@extends('layouts.app')

@section('content')
<div class="py-8 bg-slate-950 min-h-screen text-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-cyan-950 to-slate-900 p-6 rounded-2xl border border-cyan-900/40 shadow-2xl flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        ● 3D/2.5D Digital Twin Live
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Fase 77 BIM Lifecycle & EPC
                    </span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-2">Digital Twin & BIM Lifecycle Explorer</h1>
                <p class="text-xs text-slate-400 mt-1">Model Konstruksi Ber-versi Anti-Tamper, Verifikasi Fisik WBS EPC, Visualisasi Lapisan MEP, dan Sandbox Simulasi Digital Twin.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('proptech.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition">
                    ← Smart Building IoT
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <span class="text-xs text-emerald-400 font-mono">OK</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-950/60 border border-rose-500/40 rounded-xl text-rose-200 text-sm flex items-center justify-between">
                <span>{{ session('error') }}</span>
                <span class="text-xs text-rose-400 font-mono">ERR</span>
            </div>
        @endif

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Model BIM Terdaftar</div>
                <div class="mt-2 text-2xl font-bold text-white">{{ $models->count() }} <span class="text-xs font-normal text-slate-500">model</span></div>
                <div class="mt-1 text-xs text-cyan-400">Gapless Hash Chain</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Komponen MEP & Aset</div>
                <div class="mt-2 text-2xl font-bold text-emerald-400">{{ $installedComponents }} <span class="text-xs font-normal text-slate-500">/ {{ $totalComponents }} terpasang</span></div>
                <div class="mt-1 text-xs text-slate-400">Chiller, Ducting, Pipa, Kabel</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Rata-Rata Progres WBS</div>
                <div class="mt-2 text-2xl font-bold text-amber-400">{{ $avgProgress }}%</div>
                <div class="mt-1 text-xs text-slate-400">Verifikasi fisik komponen nyata</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Active Twin Issues</div>
                <div class="mt-2 text-2xl font-bold text-rose-400">{{ $issues->where('status', 'OPEN')->count() }} <span class="text-xs font-normal text-slate-500">open</span></div>
                <div class="mt-1 text-xs text-slate-400">Terhubung Work Order Fasilitas</div>
            </div>
        </div>

        <!-- Simulation Result Banner (If active) -->
        @if($simulationResult)
            <div class="bg-slate-900/90 rounded-2xl border border-cyan-800/60 p-6 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        <h2 class="text-lg font-semibold text-white">Hasil Sandbox Simulasi Digital Twin</h2>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-800">
                        In-Memory (0 Mutasi DB)
                    </span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 font-mono text-xs">
                    <div class="p-3 bg-slate-950/70 rounded-lg border border-slate-800">
                        <span class="text-slate-400">Suhu Ambien:</span>
                        <div class="text-white font-bold text-base mt-1">{{ $simulationResult['ambient_temp_c'] }}°C</div>
                    </div>
                    <div class="p-3 bg-slate-950/70 rounded-lg border border-slate-800">
                        <span class="text-slate-400">Proyeksi Suhu Ruang:</span>
                        <div class="text-cyan-400 font-bold text-base mt-1">{{ $simulationResult['projected_room_temp_c'] }}°C</div>
                    </div>
                    <div class="p-3 bg-slate-950/70 rounded-lg border border-slate-800">
                        <span class="text-slate-400">Total Aliran Udara (CFM):</span>
                        <div class="text-emerald-400 font-bold text-base mt-1">{{ number_format($simulationResult['airflow_cfm'], 0) }} CFM</div>
                    </div>
                    <div class="p-3 bg-slate-950/70 rounded-lg border border-slate-800">
                        <span class="text-slate-400">Record Database Termutasi:</span>
                        <div class="text-white font-bold text-base mt-1">{{ $simulationResult['db_records_modified'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Workspace: Visual Components & BIM Models -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left 2 Cols: 2.5D Components & Models Table -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Twin Components 2.5D Visual Layer -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-white">Visualisasi Komponen MEP & Progres Fisik WBS</h2>
                        <span class="text-xs text-slate-400 font-mono">LOD 300/400</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($components as $comp)
                            <div class="p-4 bg-slate-950/80 rounded-xl border border-slate-800 hover:border-slate-700 transition">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300">{{ $comp->component_code }}</span>
                                            <span class="text-white font-semibold text-sm">{{ $comp->name }}</span>
                                            <span class="text-xs font-mono text-cyan-400">[{{ $comp->category }}]</span>
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            Lokasi: <span class="text-slate-200">{{ $comp->location_spec ?? 'Main Plenum' }}</span> • 
                                            Node WBS: <span class="text-slate-200 font-mono">{{ $comp->wbs_node_code ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-xs font-mono font-bold text-white">{{ $comp->completion_pct }}%</div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $comp->status === 'INSTALLED' || $comp->status === 'OPERATIONAL' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-800 text-slate-300' }}">
                                                {{ $comp->status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Progress Bar -->
                                <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden mt-3">
                                    <div class="bg-cyan-500 h-2 rounded-full transition-all duration-500" style="width: {{ $comp->completion_pct }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 text-sm">Belum ada komponen MEP terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- BIM Models Hash Chain Audit Table -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white mb-4">Model BIM Ber-versi & Rantai Hash Anti-Tamper</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-slate-400 uppercase font-mono text-[10px]">
                                <tr>
                                    <th class="p-3">Model</th>
                                    <th class="p-3">Versi</th>
                                    <th class="p-3">Previous Hash</th>
                                    <th class="p-3">Current Hash</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 font-mono">
                                @forelse($models as $model)
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="p-3 font-semibold text-white">{{ $model->name }} ({{ $model->project_code }})</td>
                                        <td class="p-3 text-cyan-400">v{{ $model->version }}</td>
                                        <td class="p-3 text-slate-500" title="{{ $model->previous_hash }}">
                                            {{ substr($model->previous_hash ?? str_repeat('0', 64), 0, 12) }}...
                                        </td>
                                        <td class="p-3 text-emerald-400" title="{{ $model->current_hash }}">
                                            {{ substr($model->current_hash, 0, 12) }}...
                                        </td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $model->status === 'APPROVED' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-800 text-slate-400' }}">
                                                {{ $model->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-slate-500">Belum ada model BIM terdaftar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Col: Sandbox Controls, CIP Capitalize & Issues -->
            <div class="space-y-6">
                
                <!-- Airflow Sandbox Simulation Control -->
                @if($models->isNotEmpty())
                    @php $primaryModel = $models->first(); @endphp
                    <div class="bg-slate-900/90 rounded-2xl border border-cyan-900/50 p-6 shadow-xl">
                        <h2 class="text-base font-semibold text-white mb-2">Sandbox Simulasi Airflow</h2>
                        <p class="text-xs text-slate-400 mb-4">Jalankan komputasi simulasi termal & ventilasi secara in-memory.</p>
                        
                        <form action="{{ route('proptech.bim.simulate', $primaryModel) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="text-xs text-slate-300">Suhu Ambien Luar (°C):</label>
                                <input type="number" step="0.5" name="ambient_temp_c" value="33.0" min="15" max="50" class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono">
                            </div>
                            <button type="submit" class="w-full py-2 px-4 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold rounded-lg transition shadow flex items-center justify-center gap-1.5">
                                <span>⚡ Jalankan Simulasi Sandbox</span>
                            </button>
                        </form>
                    </div>
                @endif

                <!-- CIP Capitalization Form -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-base font-semibold text-white mb-2">Kapitalisasi Milestone WBS</h2>
                    <p class="text-xs text-slate-400 mb-4">Posting CIP ke Fixed Assets berdasarkan verifikasi progres fisik 100%.</p>
                    
                    <form action="{{ route('proptech.bim.capitalize') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs text-slate-300">Kode Node WBS:</label>
                            <input type="text" name="wbs_node_code" placeholder="mis. WBS-MEP-HVAC" required class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300">Nilai Milestone (IDR):</label>
                            <input type="number" name="milestone_value_idr" value="500000000" min="1000" step="1000" required class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition shadow flex items-center justify-center gap-1.5">
                            <span>🏛️ Kapitalisasi ke Fixed Assets</span>
                        </button>
                    </form>
                </div>

                <!-- Twin Issues / Work Orders -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-base font-semibold text-white mb-3">Twin Issues & Work Order</h2>
                    <div class="space-y-2.5 max-h-[350px] overflow-y-auto pr-1 text-xs">
                        @forelse($issues as $issue)
                            <div class="p-3 bg-slate-950/80 rounded-lg border border-slate-800">
                                <div class="flex justify-between items-start">
                                    <span class="font-mono font-bold text-rose-400">{{ $issue->issue_code }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">{{ $issue->status }}</span>
                                </div>
                                <div class="text-slate-300 mt-1 font-medium">{{ $issue->issue_type }} — {{ $issue->description }}</div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    Komponen: {{ $issue->component?->name }} • WO ID: {{ $issue->facility_work_order_id ?? 'N/A' }}
                                </div>
                            </div>
                        @empty
                            <div class="text-slate-500 text-xs text-center p-4">Tidak ada issue aktif.</div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
