<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight flex items-center gap-2">
                <span class="text-cyan-400">❄️🚁</span> {{ __('Autonomous Cold-Chain, Blockchain & Drone Robotics') }}
            </h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                Fase 80 — Autonomous Verified
            </span>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-950 min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div id="alert-success" class="p-4 bg-emerald-900/50 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm flex items-center gap-2 shadow-lg">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-slate-400 font-medium">Active Breach Holds</div>
                    <div class="text-2xl font-bold text-rose-400 mt-2" id="kpi-active-holds">{{ number_format($stats['active_holds']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">Total: {{ $stats['total_breaches'] }} recorded breaches</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-amber-400 font-medium">Escrow Amount Held</div>
                    <div class="text-2xl font-bold text-amber-300 mt-2" id="kpi-held-amount">Rp {{ number_format($stats['total_held_idr'], 0, ',', '.') }}</div>
                    <div class="text-xs text-slate-500 mt-1">Terkunci di lgx:dispute_escrow:IDR</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-cyan-400 font-medium">Drone Fleet Units</div>
                    <div class="text-2xl font-bold text-cyan-300 mt-2" id="kpi-total-drones">{{ number_format($stats['total_drones']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">
                        <span class="text-emerald-400 font-semibold">{{ $stats['idle_drones'] }}</span> Idle / 
                        <span class="text-cyan-400 font-semibold">{{ $stats['total_drones'] - $stats['idle_drones'] }}</span> Active
                    </div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-indigo-400 font-medium">Last-Mile Missions</div>
                    <div class="text-2xl font-bold text-indigo-300 mt-2" id="kpi-total-missions">{{ number_format($stats['active_missions'] + $stats['completed_missions']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">
                        <span class="text-amber-400 font-semibold">{{ $stats['active_missions'] }}</span> In Flight / 
                        <span class="text-emerald-400 font-semibold">{{ $stats['completed_missions'] }}</span> Delivered
                    </div>
                </div>
            </div>

            <!-- Drone Fleet Grid & Dispatch Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Drone Fleet Status Radar -->
                <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg lg:col-span-1">
                    <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                        <span>🛰️</span> Armada Drone Aktif
                    </h3>
                    <div class="space-y-3">
                        @forelse($drones as $drn)
                            <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-xl flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-mono font-bold text-cyan-400">{{ $drn->drone_code }}</div>
                                    <div class="text-xs text-slate-300">{{ $drn->model_name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Payload: {{ $drn->max_payload_kg }}kg | Range: {{ $drn->max_range_km }}km</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold {{ $drn->battery_percent > 30 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        ⚡ {{ $drn->battery_percent }}%
                                    </div>
                                    <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $drn->status === 'IDLE' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' }}">
                                        {{ $drn->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-slate-500 text-xs py-4">Belum ada unit drone terdaftar di hub ini.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Dispatch Drone Form -->
                <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg lg:col-span-2">
                    <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                        <span>🚀</span> Dispatch Misi Drone Last-Mile
                    </h3>
                    <form id="form-dispatch-drone" action="{{ route('logistics.autonomous.dispatch') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="drone_unit_id" class="block text-xs font-medium text-slate-400">Pilih Drone Unit</label>
                                <select id="drone_unit_id" name="drone_unit_id" class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                                    @foreach($drones->where('status', 'IDLE') as $drn)
                                        <option value="{{ $drn->id }}">{{ $drn->drone_code }} - {{ $drn->model_name }} ({{ $drn->battery_percent }}%)</option>
                                    @endforeach
                                    @if($drones->where('status', 'IDLE')->isEmpty())
                                        <option value="" disabled selected>Tidak ada drone IDLE saat ini</option>
                                    @endif
                                </select>
                            </div>
                            <div>
                                <label for="shipment_code" class="block text-xs font-medium text-slate-400">Kode Pengiriman</label>
                                <input type="text" id="shipment_code" name="shipment_code" value="SHP-DRN-{{ time() }}" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                            </div>
                            <div>
                                <label for="package_weight_kg" class="block text-xs font-medium text-slate-400">Berat Paket (kg, max 5.0kg)</label>
                                <input type="number" step="0.1" id="package_weight_kg" name="package_weight_kg" value="2.5" min="0.1" max="5.0" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                            </div>
                            <div>
                                <label for="distance_km" class="block text-xs font-medium text-slate-400">Jarak Tempuh (km, max 15.0km)</label>
                                <input type="number" step="0.1" id="distance_km" name="distance_km" value="6.5" min="0.1" max="15.0" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                            </div>
                            <div class="md:col-span-2">
                                <label for="destination_geo_hash" class="block text-xs font-medium text-slate-400">Destination Geo-Hash Target</label>
                                <input type="text" id="destination_geo_hash" name="destination_geo_hash" value="qqs387v-banjarmasin-dropzone-01" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2 font-mono">
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" id="btn-submit-drone-mission" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl font-medium text-sm transition shadow-lg shadow-cyan-900/30">
                                🛫 Luncurkan Misi Drone
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Drone Missions Table -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <span>🗺️</span> Misi Drone & Digital Proof of Delivery (POD)
                    </h3>
                    <span class="text-xs text-slate-400">Total: {{ $missions->total() }} misi</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Kode Misi</th>
                                <th class="px-4 py-3">Drone Unit</th>
                                <th class="px-4 py-3">Pengiriman</th>
                                <th class="px-4 py-3">Muatan & Jarak</th>
                                <th class="px-4 py-3">Geo-Hash Target</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">POD Kriptografis</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($missions as $msn)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-100 font-mono text-xs">{{ $msn->mission_code }}</td>
                                    <td class="px-4 py-3 text-xs font-mono text-cyan-400">{{ $msn->drone?->drone_code ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $msn->shipment_code }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $msn->package_weight_kg }} kg / {{ $msn->distance_km }} km</td>
                                    <td class="px-4 py-3 text-xs font-mono text-slate-400">{{ $msn->destination_geo_hash }}</td>
                                    <td class="px-4 py-3">
                                        @if($msn->status === 'DELIVERED')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">DELIVERED</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30">{{ $msn->status }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        @if($msn->pod_signature_hash)
                                            <div class="text-emerald-400 truncate max-w-[120px]" title="{{ $msn->pod_signature_hash }}">✓ {{ $msn->pod_signature_hash }}</div>
                                        @else
                                            <span class="text-slate-500">In Transit...</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($msn->status === 'DISPATCHED')
                                            <form action="{{ route('logistics.autonomous.deliver', $msn->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="pod_geo_hash" value="{{ $msn->destination_geo_hash }}">
                                                <button type="submit" id="btn-deliver-{{ $msn->id }}" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-medium transition shadow">
                                                    Selesaikan POD
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-slate-500 text-xs">Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500 text-sm">
                                        Belum ada misi drone yang tercatat. Silakan gunakan form dispatch di atas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $missions->links() }}
                </div>
            </div>

            <!-- Temperature Breach Holds Table -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <span>⚠️</span> Log Pelanggaran Suhu Cold-Chain & Payment Holds
                    </h3>
                    <span class="text-xs text-slate-400">Total: {{ $breaches->total() }} catatan</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Kode Breach</th>
                                <th class="px-4 py-3">Pengiriman</th>
                                <th class="px-4 py-3">Carrier ID</th>
                                <th class="px-4 py-3">Suhu Aktual / Batas</th>
                                <th class="px-4 py-3">Durasi</th>
                                <th class="px-4 py-3">Escrow Tertahan</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($breaches as $brc)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-100 font-mono text-xs">{{ $brc->breach_code }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $brc->shipment_code }}</td>
                                    <td class="px-4 py-3 text-xs font-mono">#{{ $brc->carrier_id }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-rose-400">
                                        {{ $brc->recorded_temp_c }} °C <span class="text-slate-500 font-normal">/ max {{ $brc->max_allowed_temp_c }} °C</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-amber-400">{{ $brc->duration_minutes }} menit</td>
                                    <td class="px-4 py-3 text-xs font-bold text-amber-300">
                                        Rp {{ number_format($brc->hold_amount_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($brc->status === 'HELD')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/20 text-rose-400 border border-rose-500/30">HELD IN ESCROW</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">RELEASED</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($brc->status === 'HELD')
                                            <form action="{{ route('logistics.autonomous.breaches.release', $brc->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" id="btn-release-{{ $brc->id }}" class="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-medium transition shadow">
                                                    Lepas Hold
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-slate-500 text-xs">Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500 text-sm">
                                        Tidak ada catatan pelanggaran suhu cold-chain yang aktif.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $breaches->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
