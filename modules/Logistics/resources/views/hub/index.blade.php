<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-white tracking-tight flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-gradient-to-tr from-amber-500/20 to-orange-500/20 border border-amber-500/30 text-amber-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </span>
                    Operasi Fasilitas Hub: {{ $hub->name }}
                </h2>
                <p class="text-sm text-slate-400 mt-1">
                    Kode: <span class="font-mono text-amber-300 font-semibold">{{ $hub->code }}</span> &bull;
                    Kota: {{ $hub->city }}, {{ $hub->province }} &bull;
                    Zona Waktu: {{ $hub->timezone }}
                </p>
            </div>

            @if(count($allHubs) > 1)
                <form method="GET" action="{{ route('logistics.hub.index') }}" class="w-full sm:w-auto">
                    <div class="flex items-center gap-2">
                        <label for="hub_id" class="text-xs text-slate-400 whitespace-nowrap">Pilih Hub:</label>
                        <select name="hub_id" id="hub_id" onchange="this.form.submit()" class="bg-slate-800 border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-amber-500">
                            @foreach($allHubs as $h)
                                <option value="{{ $h->id }}" {{ $h->id === $hub->id ? 'selected' : '' }}>
                                    {{ $h->name }} ({{ $h->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6" x-data="{ activeTab: 'inbound' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Flash Alerts -->
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm flex items-center justify-between shadow-lg">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="p-4 rounded-xl bg-rose-950/90 border border-rose-500 text-rose-200 text-sm flex items-start gap-3 shadow-xl animate-pulse">
                    <svg class="w-6 h-6 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <div class="font-bold text-white text-base">PERINGATAN MISSORT / KENDALA RUTE</div>
                        <div class="mt-1">{{ session('warning') }}</div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm flex items-center gap-3 shadow-lg">
                    <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Hub Statistics Today -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
                    <div class="text-xs font-medium text-slate-400">Inbound Hari Ini</div>
                    <div class="text-2xl font-bold font-mono text-cyan-400 mt-1">{{ number_format($todayInbound) }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
                    <div class="text-xs font-medium text-slate-400">Disortir Hari Ini</div>
                    <div class="text-2xl font-bold font-mono text-amber-400 mt-1">{{ number_format($todaySorted) }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
                    <div class="text-xs font-medium text-slate-400">Outbound Hari Ini</div>
                    <div class="text-2xl font-bold font-mono text-emerald-400 mt-1">{{ number_format($todayOutbound) }}</div>
                </div>
                <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
                    <div class="text-xs font-medium text-slate-400">Missort Terdeteksi</div>
                    <div class="text-2xl font-bold font-mono {{ $todayMissorts > 0 ? 'text-rose-400 font-extrabold' : 'text-slate-400' }} mt-1">
                        {{ number_format($todayMissorts) }}
                    </div>
                </div>
            </div>

            <!-- Operations Tabs (Mobile Friendly) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
                <div class="flex border-b border-slate-800 bg-slate-950/60 p-1.5 gap-1.5">
                    <button type="button"
                            @click="activeTab = 'inbound'"
                            :class="activeTab === 'inbound' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow' : 'text-slate-400 hover:text-white border-transparent'"
                            class="flex-1 py-3 px-4 rounded-xl text-sm font-semibold border flex items-center justify-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                        <span>1. Inbound Scan</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'sort'"
                            :class="activeTab === 'sort' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow' : 'text-slate-400 hover:text-white border-transparent'"
                            class="flex-1 py-3 px-4 rounded-xl text-sm font-semibold border flex items-center justify-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                        <span>2. Sortir & Putaway</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'outbound'"
                            :class="activeTab === 'outbound' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow' : 'text-slate-400 hover:text-white border-transparent'"
                            class="flex-1 py-3 px-4 rounded-xl text-sm font-semibold border flex items-center justify-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                        <span>3. Outbound & Muat</span>
                    </button>
                </div>

                <div class="p-6">
                    <!-- Tab 1: Inbound Scan -->
                    <div x-show="activeTab === 'inbound'" class="space-y-4">
                        <div class="max-w-xl">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <span>Pindai Masuk Kargo (Inbound)</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Pindai barcode nomor resi atau ketik nomor resi SRX. Sistem akan otomatis memvalidasi apakah fasilitas ini terdaftar dalam rute resmi kargo. Jika di luar rute, sistem otomatis membuat event <span class="text-rose-400 font-semibold">MISSORT</span>.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('logistics.hub.inbound') }}" class="max-w-xl space-y-4">
                            @csrf
                            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
                            <div>
                                <label for="inbound_tracking" class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
                                    Nomor Resi / Barcode Kargo
                                </label>
                                <div class="relative">
                                    <input type="text"
                                           name="tracking_number"
                                           id="inbound_tracking"
                                           required
                                           autofocus
                                           placeholder="Contoh: SRX00010000018"
                                           class="w-full bg-slate-800/90 border border-slate-700 rounded-xl px-4 py-3.5 text-lg font-mono text-white placeholder-slate-500 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 shadow-inner">
                                    <span class="absolute right-3 top-3.5 text-slate-500">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                    </span>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-base shadow-lg shadow-amber-600/30 transition flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Proses Inbound Scan
                            </button>
                        </form>
                    </div>

                    <!-- Tab 2: Sortir -->
                    <div x-show="activeTab === 'sort'" style="display: none;" class="space-y-4">
                        <div class="max-w-xl">
                            <h3 class="text-lg font-bold text-white">Sortir Kargo ke Jalur / Bin</h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Kelompokkan kargo yang sudah di-inbound ke dalam bin/bay/gate tujuan sebelum dimuat ke armada penghubung.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('logistics.hub.sort') }}" class="max-w-xl space-y-4">
                            @csrf
                            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
                            <div>
                                <label for="sort_tracking" class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
                                    Nomor Resi Kargo
                                </label>
                                <input type="text"
                                       name="tracking_number"
                                       id="sort_tracking"
                                       required
                                       placeholder="SRX..."
                                       class="w-full bg-slate-800/90 border border-slate-700 rounded-xl px-4 py-3 font-mono text-white placeholder-slate-500 focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div>
                                <label for="sort_bay" class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
                                    Zona / Bin / Bay Sortir
                                </label>
                                <input type="text"
                                       name="sort_bay"
                                       id="sort_bay"
                                       required
                                       placeholder="Contoh: Bay A-12 (Surabaya), Gate 3 Laut, Rak Transit B"
                                       class="w-full bg-slate-800/90 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:ring-2 focus:ring-amber-500">
                            </div>

                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-base shadow-lg shadow-amber-600/30 transition flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                Simpan Data Sortir
                            </button>
                        </form>
                    </div>

                    <!-- Tab 3: Outbound & Load -->
                    <div x-show="activeTab === 'outbound'" style="display: none;" class="space-y-4">
                        <div class="max-w-xl">
                            <h3 class="text-lg font-bold text-white">Outbound Scan & Muat ke Jadwal</h3>
                            <p class="text-xs text-slate-400 mt-1">
                                Pindai kargo dan masukkan ke manifes keberangkatan jadwal aktif yang berangkat dari hub {{ $hub->name }}. Status pengiriman akan berubah menjadi <span class="text-cyan-400 font-semibold">Dalam Perjalanan (InTransit)</span>.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('logistics.hub.outbound') }}" class="max-w-xl space-y-4">
                            @csrf
                            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
                            <div>
                                <label for="outbound_tracking" class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
                                    Nomor Resi Kargo
                                </label>
                                <input type="text"
                                       name="tracking_number"
                                       id="outbound_tracking"
                                       required
                                       placeholder="SRX..."
                                       class="w-full bg-slate-800/90 border border-slate-700 rounded-xl px-4 py-3 font-mono text-white placeholder-slate-500 focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div>
                                <label for="schedule_id" class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
                                    Pilih Jadwal Keberangkatan Dari Hub Ini
                                </label>
                                <select name="schedule_id" id="schedule_id" required class="w-full bg-slate-800/90 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-amber-500 text-sm">
                                    <option value="">-- Pilih Jadwal Trip --</option>
                                    @foreach($departingSchedules as $sched)
                                        <option value="{{ $sched->id }}">
                                            Jadwal #{{ $sched->schedule_number }} &bull; {{ $sched->mode->label() }} &bull; Tujuan: {{ $sched->destination?->name }} (ETD: {{ $sched->etd->format('d M H:i') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-base shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Pindai Outbound & Muat Armada
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Chain of Custody Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-white text-base">Aktivitas Terkini di Hub Ini</h3>
                        <p class="text-xs text-slate-400">Rantai peristiwa lacak balak (Chain of Custody) yang tercatat di {{ $hub->name }}</p>
                    </div>
                    <span class="text-xs font-mono text-slate-500">20 Event Terakhir</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-950/60 text-slate-400 border-b border-slate-800 font-semibold">
                            <tr>
                                <th class="px-6 py-3.5">Waktu</th>
                                <th class="px-6 py-3.5">No. Resi</th>
                                <th class="px-6 py-3.5">Peristiwa</th>
                                <th class="px-6 py-3.5">Keterangan</th>
                                <th class="px-6 py-3.5">Petugas</th>
                                <th class="px-6 py-3.5 font-mono">Hash Ringkas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($recentEvents as $ev)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-6 py-3.5 text-xs text-slate-400 whitespace-nowrap">
                                        {{ $ev->occurred_at->format('d M Y H:i:s') }}
                                    </td>
                                    <td class="px-6 py-3.5 font-mono font-semibold text-amber-300 whitespace-nowrap">
                                        <a href="{{ route('logistics.shipments.show', $ev->shipment_id) }}" class="hover:underline">
                                            {{ $ev->shipment?->tracking_number ?? '-' }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        @if($ev->event_type === 'MISSORT')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-950 text-rose-300 border border-rose-600 animate-pulse">
                                                MISSORT
                                            </span>
                                        @elseif($ev->event_type === 'HUB_INBOUND')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                INBOUND
                                            </span>
                                        @elseif($ev->event_type === 'SORTED')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-950 text-amber-300 border border-amber-800">
                                                SORTED
                                            </span>
                                        @elseif($ev->event_type === 'HUB_OUTBOUND')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                OUTBOUND
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                                {{ $ev->event_type }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-300">
                                        {{ $ev->description }}
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-400 whitespace-nowrap">
                                        {{ $ev->actor?->name ?? 'Sistem' }}
                                    </td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-slate-500 whitespace-nowrap">
                                        {{ substr($ev->hash, 0, 10) }}...
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-slate-500 text-sm">
                                        Belum ada aktivitas lacak balak di fasilitas hub ini hari ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
