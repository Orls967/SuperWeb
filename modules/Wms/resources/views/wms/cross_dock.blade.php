<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 dark:text-slate-100 leading-tight">
                    WMS Cross-Docking &amp; Penjadwalan Dermaga
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Deteksi bentrok jendela dermaga, skor SLA turnaround, dan evaluasi risiko carrier cutoff.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('wms.picking')">
                    Wave Picking
                </x-ds.button>
                <x-ds.button variant="primary" size="sm" :href="route('wms.docks.export')">
                    Ekspor CSV Dermaga
                </x-ds.button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $activeDocks = (int) ($dockEvaluation['active_appointments'] ?? count($docks ?? []));
                $conflictCount = (int) ($dockEvaluation['conflict_count'] ?? 0);
                $avgSlaBps = (int) ($dockEvaluation['average_sla_score_bps'] ?? 10000);
                $highRiskCount = (int) ($dockEvaluation['high_risk_count'] ?? 0);
                $dockUtilPermil = (int) ($analytics['dock_utilization_permil'] ?? 0);
                $quarantinePermil = (int) ($analytics['quarantine_release_rate_permil'] ?? 0);

                $dockHeaders = [
                    ['key' => 'dock_door', 'label' => 'Pintu Dock'],
                    ['key' => 'warehouse_code', 'label' => 'Gudang'],
                    ['key' => 'reference', 'label' => 'Referensi'],
                    ['key' => 'direction', 'label' => 'Arah'],
                    ['key' => 'window', 'label' => 'Jendela Waktu'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'sla_score_bps', 'label' => 'SLA (bps)', 'align' => 'right', 'tabular' => true],
                    ['key' => 'cutoff_risk', 'label' => 'Risiko Cutoff'],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ds.stat
                    label="Jadwal Dock Aktif"
                    :value="$activeDocks"
                    :delta="count($warehouses ?? []) . ' gudang aktif'"
                    deltaDirection="neutral"
                    hint="Inbound, outbound &amp; cross-dock"
                    icon="cube"
                />
                <x-ds.stat
                    label="Utilisasi Dermaga"
                    :value="number_format($dockUtilPermil / 10, 1) . '%'"
                    :delta="$conflictCount . ' bentrok'"
                    :deltaDirection="$conflictCount > 0 ? 'down' : 'up'"
                    hint="Kapasitas shift harian"
                    icon="chart-bar"
                />
                <x-ds.stat
                    label="Skor SLA Turnaround"
                    :value="number_format($avgSlaBps / 100, 1) . '%'"
                    :delta="$highRiskCount . ' berisiko cutoff'"
                    :deltaDirection="$highRiskCount > 0 ? 'down' : 'up'"
                    hint="Rata-rata bps penjadwalan"
                    icon="check-circle"
                />
                <x-ds.stat
                    label="Rilis Karantina"
                    :value="number_format($quarantinePermil / 10, 1) . '%'"
                    hint="Tingkat pelepasan stok karantina"
                    icon="check-circle"
                />
            </div>

            @if(! empty($dockEvaluation['conflicts']))
                <x-ds.card
                    title="Rekomendasi Relokasi Bentrok Dermaga"
                    subtitle="Pemindahan otomatis ke pintu dermaga cadangan terdekat yang bebas bentrok"
                >
                    <x-slot name="header">
                        <x-ds.badge variant="warning">{{ $conflictCount }} Bentrok Terdeteksi</x-ds.badge>
                    </x-slot>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($dockEvaluation['conflicts'] as $conflict)
                            <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-xs space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-900">
                                        {{ $conflict['dock_door'] }} · Overlap {{ $conflict['overlap_minutes'] }} menit
                                    </span>
                                    <x-ds.badge variant="info">
                                        Saran: {{ $conflict['recommended_door_for_b'] ?? 'Geser Slot' }}
                                    </x-ds.badge>
                                </div>
                                <p class="text-slate-600">
                                    Janji #{{ $conflict['appointment_a'] }} vs #{{ $conflict['appointment_b'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </x-ds.card>
            @endif

            <x-ds.card
                title="Jadwal Dermaga &amp; Cross-Docking"
                subtitle="Pantauan jendela waktu, SLA turnaround, dan mitigasi carrier cutoff"
            >
                <x-slot name="header">
                    <x-ds.badge :variant="$highRiskCount > 0 ? 'warning' : 'success'">
                        Risiko Cutoff Tinggi: {{ $highRiskCount }}
                    </x-ds.badge>
                </x-slot>

                <x-ds.table
                    :headers="$dockHeaders"
                    :rows="$dockRows ?? []"
                    :exportable="true"
                    :exportUrl="route('wms.docks.export')"
                    emptyMessage="Belum ada janji temu dermaga / cross-dock."
                />
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
