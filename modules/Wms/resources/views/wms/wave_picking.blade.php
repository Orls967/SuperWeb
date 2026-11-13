<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 dark:text-slate-100 leading-tight">
                    WMS Wave Picking &amp; Optimasi Rute
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Pengelompokan zona, jarak Manhattan terpendek, dan deteksi kemacetan pick-face.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('wms.docks')">
                    Manajemen Dermaga
                </x-ds.button>
                <x-ds.button variant="primary" size="sm" :href="route('wms.picking.export')">
                    Ekspor CSV Picking
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
                $totalWaves = (int) ($analytics['total_waves'] ?? count($waves ?? []));
                $openPickTasks = (int) ($analytics['open_pick_tasks'] ?? count($tasks ?? []));
                $completionPermil = (int) ($analytics['picking_wave_completion_permil'] ?? 0);
                $slottingPermil = (int) ($analytics['slotting_abc_accuracy_permil'] ?? 0);
                $estimatedSeconds = (int) ($pickOptimization['estimated_total_seconds'] ?? 0);
                $totalDistance = (int) ($pickOptimization['total_manhattan_distance'] ?? 0);
                $congestionCount = (int) ($pickOptimization['congestion_count'] ?? 0);

                $waveHeaders = [
                    ['key' => 'wave_code', 'label' => 'Kode Wave'],
                    ['key' => 'strategy', 'label' => 'Strategi'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'task_count', 'label' => 'Tugas', 'align' => 'right', 'tabular' => true],
                    ['key' => 'total_qty', 'label' => 'Total Unit', 'align' => 'right', 'tabular' => true],
                    ['key' => 'estimated_seconds', 'label' => 'Est. Detik', 'align' => 'right', 'tabular' => true],
                    ['key' => 'released_at', 'label' => 'Waktu Rilis', 'align' => 'right', 'tabular' => true],
                ];

                $zoneRows = array_map(static fn (array $plan): array => [
                    'zone_code' => $plan['zone_code'],
                    'strategy' => $plan['strategy'],
                    'aisle_count' => $plan['aisle_count'],
                    'stop_count' => $plan['stop_count'],
                    'manhattan_distance' => $plan['manhattan_distance'].' m',
                    'estimated_seconds' => $plan['estimated_seconds'].' dtk',
                ], $pickOptimization['zone_plans'] ?? []);

                $zoneHeaders = [
                    ['key' => 'zone_code', 'label' => 'Zona'],
                    ['key' => 'strategy', 'label' => 'Algoritma'],
                    ['key' => 'aisle_count', 'label' => 'Lorong', 'align' => 'right', 'tabular' => true],
                    ['key' => 'stop_count', 'label' => 'Titik Pick', 'align' => 'right', 'tabular' => true],
                    ['key' => 'manhattan_distance', 'label' => 'Jarak Manhattan', 'align' => 'right', 'tabular' => true],
                    ['key' => 'estimated_seconds', 'label' => 'Estimasi Waktu', 'align' => 'right', 'tabular' => true],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ds.stat
                    label="Total Wave"
                    :value="$totalWaves"
                    hint="Wave outbound tercatat"
                    icon="cube"
                />
                <x-ds.stat
                    label="Tugas Pick Aktif"
                    :value="$openPickTasks"
                    :delta="number_format($completionPermil / 10, 1) . '% selesai'"
                    deltaDirection="up"
                    hint="Rasio penyelesaian wave"
                    icon="check-circle"
                />
                <x-ds.stat
                    label="Jarak &amp; Estimasi Rute"
                    :value="$totalDistance . ' m'"
                    :delta="$estimatedSeconds . ' detik'"
                    deltaDirection="neutral"
                    hint="Optimasi S-Shape / Largest-Gap"
                    icon="chart-bar"
                />
                <x-ds.stat
                    label="Akurasi Slotting ABC"
                    :value="number_format($slottingPermil / 10, 1) . '%'"
                    :delta="$congestionCount . ' titik macet'"
                    :deltaDirection="$congestionCount > 0 ? 'down' : 'up'"
                    hint="Deteksi kepadatan pick-face"
                    icon="chart-bar"
                />
            </div>

            <x-ds.card
                title="Rencana Lintasan Pick per Zona"
                subtitle="Urutan rute terpendek berbasis PickPathPlanner &amp; jarak Manhattan"
            >
                <x-slot name="header">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ds.badge :variant="$congestionCount > 0 ? 'warning' : 'success'">
                            Kemacetan Pick-Face: {{ $congestionCount }}
                        </x-ds.badge>
                    </div>
                </x-slot>

                <x-ds.table
                    :headers="$zoneHeaders"
                    :rows="$zoneRows"
                    emptyMessage="Belum ada tugas pick aktif untuk dioptimasi."
                />

                @if(! empty($pickOptimization['congested_pick_faces']))
                    <div class="mt-4 space-y-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                            Peringatan Kemacetan Pick-Face
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($pickOptimization['congested_pick_faces'] as $alert)
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 flex items-center justify-between gap-2 text-xs">
                                    <div>
                                        <span class="font-semibold text-slate-900">{{ $alert['zone_code'] }} · {{ $alert['bin_id'] }}</span>
                                        <p class="text-slate-600 mt-0.5">{{ $alert['task_count'] }} tugas ({{ $alert['total_qty'] }} unit)</p>
                                    </div>
                                    <x-ds.badge :variant="$alert['severity'] === 'HIGH' ? 'danger' : 'warning'">
                                        +{{ $alert['congestion_penalty_seconds'] }} dtk
                                    </x-ds.badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ds.card>

            <x-ds.card
                title="Daftar Outbound Wave Picking"
                subtitle="Ringkasan status rilis wave dan estimasi durasi eksekusi"
            >
                <x-ds.table
                    :headers="$waveHeaders"
                    :rows="$waveRows ?? []"
                    :exportable="true"
                    :exportUrl="route('wms.picking.export')"
                    emptyMessage="Belum ada outbound wave aktif."
                />
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
