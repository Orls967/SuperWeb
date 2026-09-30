<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Facility Management & Work Orders (SPK)
                </h2>
                <p class="text-sm text-slate-500 mt-1">Pemeliharaan berkala aset gedung (HVAC, Lift, Genset, Plumbing) & Kanban perintah kerja perbaikan</p>
            </div>
            <div class="flex items-center gap-3">
                <form action="{{ route('mall.facilities.generate-pm') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        <span>Jadwalkan PM Rutin (Auto-Generate)</span>
                    </button>
                </form>
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

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Aset Gedung</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">{{ $assetStats['total'] }} Unit</h3>
                    <p class="text-xs text-slate-500 mt-1">HVAC, lift, genset, plumbing</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Beroperasi Normal</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-2">{{ $assetStats['operational'] }} Unit</h3>
                    <p class="text-xs text-slate-500 mt-1">Status 100% prima</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sedang Diservis</p>
                    <h3 class="text-2xl font-black text-amber-500 mt-2">{{ $assetStats['maintenance'] }} Unit</h3>
                    <p class="text-xs text-slate-500 mt-1">Perawatan berjalan</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Jatuh Tempo PM</p>
                    <h3 class="text-2xl font-black text-indigo-600 mt-2">{{ $assetStats['pm_due'] }} Unit</h3>
                    <p class="text-xs text-slate-500 mt-1">Siap diterbitkan SPK</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pelanggaran SLA</p>
                    <h3 class="text-2xl font-black {{ $slaBreachesCount > 0 ? 'text-rose-600' : 'text-slate-800' }} mt-2">{{ $slaBreachesCount }} Kasus</h3>
                    <p class="text-xs text-slate-500 mt-1">Keterlambatan penyelesaian</p>
                </div>
            </div>

            <!-- Kanban Board 4 Kolom -->
            <div>
                <h3 class="font-extrabold text-slate-800 text-lg mb-4">Kanban Perintah Kerja (Work Orders)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Kolom 1: Terbuka (Open) -->
                    <div class="bg-slate-100 rounded-2xl p-4 border border-slate-200">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="font-extrabold text-slate-700 text-sm">Menunggu Teknisi</h4>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-200 text-slate-700">{{ $kanban['open']->count() }}</span>
                        </div>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            @forelse($kanban['open'] as $wo)
                                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 hover:border-slate-400 transition-all">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-mono font-bold text-slate-500">{{ $wo->order_number }}</span>
                                        <span class="px-2 py-0.5 font-bold rounded {{ $wo->priority->value === 'emergency' ? 'bg-rose-100 text-rose-700' : ($wo->priority->value === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">
                                            {{ $wo->priority->label() }}
                                        </span>
                                    </div>
                                    <h5 class="font-bold text-slate-800 text-sm mt-2">{{ $wo->title }}</h5>
                                    @if($wo->asset)
                                        <p class="text-xs text-slate-400 mt-1">Aset: {{ $wo->asset->name }} ({{ $wo->asset->asset_tag }})</p>
                                    @endif
                                    @if($wo->sla_breached)
                                        <div class="mt-2 p-1.5 rounded bg-rose-50 text-rose-700 text-[10px] font-bold">
                                            ⚠️ SLA Terlewati (> {{ $wo->sla_hours }} jam)
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-6">Tidak ada pekerjaan terbuka.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Kolom 2: Sedang Dikerjakan (In Progress) -->
                    <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-200">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="font-extrabold text-blue-900 text-sm">Sedang Dikerjakan</h4>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-200 text-blue-800">{{ $kanban['in_progress']->count() }}</span>
                        </div>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            @forelse($kanban['in_progress'] as $wo)
                                <div class="p-4 bg-white rounded-xl shadow-sm border border-blue-200 hover:border-blue-400 transition-all">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-mono font-bold text-slate-500">{{ $wo->order_number }}</span>
                                        <span class="px-2 py-0.5 font-bold rounded bg-blue-100 text-blue-700">
                                            {{ $wo->priority->label() }}
                                        </span>
                                    </div>
                                    <h5 class="font-bold text-slate-800 text-sm mt-2">{{ $wo->title }}</h5>
                                    @if($wo->assignedTo)
                                        <p class="text-xs text-slate-500 mt-1">Teknisi: <strong class="text-slate-700">{{ $wo->assignedTo->name }}</strong></p>
                                    @endif
                                    @if($wo->sla_breached)
                                        <div class="mt-2 p-1.5 rounded bg-rose-50 text-rose-700 text-[10px] font-bold">
                                            ⚠️ SLA Terlewati
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-6">Tidak ada pekerjaan berjalan.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Kolom 3: Ditahan (On Hold) -->
                    <div class="bg-amber-50/50 rounded-2xl p-4 border border-amber-200">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="font-extrabold text-amber-900 text-sm">Menunggu Suku Cadang</h4>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-200 text-amber-800">{{ $kanban['on_hold']->count() }}</span>
                        </div>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            @forelse($kanban['on_hold'] as $wo)
                                <div class="p-4 bg-white rounded-xl shadow-sm border border-amber-200 hover:border-amber-400 transition-all">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-mono font-bold text-slate-500">{{ $wo->order_number }}</span>
                                        <span class="px-2 py-0.5 font-bold rounded bg-amber-100 text-amber-700">On Hold</span>
                                    </div>
                                    <h5 class="font-bold text-slate-800 text-sm mt-2">{{ $wo->title }}</h5>
                                    <p class="text-xs text-slate-500 mt-1">{{ $wo->description }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-6">Tidak ada pekerjaan tertahan.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Kolom 4: Selesai (Completed) -->
                    <div class="bg-emerald-50/50 rounded-2xl p-4 border border-emerald-200">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="font-extrabold text-emerald-900 text-sm">Selesai Dikerjakan</h4>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-200 text-emerald-800">{{ $kanban['completed']->count() }}</span>
                        </div>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            @forelse($kanban['completed'] as $wo)
                                <div class="p-4 bg-white rounded-xl shadow-sm border border-emerald-200 hover:border-emerald-400 transition-all">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-mono font-bold text-slate-500">{{ $wo->order_number }}</span>
                                        <span class="px-2 py-0.5 font-bold rounded bg-emerald-100 text-emerald-700">✓ Selesai</span>
                                    </div>
                                    <h5 class="font-bold text-slate-800 text-sm mt-2">{{ $wo->title }}</h5>
                                    <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                        <span class="text-slate-400">Total Biaya:</span>
                                        <strong class="text-slate-800">Rp {{ number_format($wo->total_cost, 0, ',', '.') }}</strong>
                                    </div>

                                    @if($wo->tenant_id && $wo->total_cost > 0 && ! $wo->billed_invoice_id)
                                        <form action="{{ route('mall.facilities.bill-tenant', $wo) }}" method="POST" class="mt-3">
                                            @csrf
                                            <button type="submit" class="w-full py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-extrabold rounded-lg border border-indigo-200 transition-all">
                                                Tagihkan ke Tenant
                                            </button>
                                        </form>
                                    @elseif($wo->billed_invoice_id)
                                        <p class="text-[10px] text-indigo-600 font-bold mt-2">✓ Ditagihkan ke Inv #{{ $wo->billed_invoice_id }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-6">Belum ada pekerjaan selesai.</p>
                            @endforelse
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
