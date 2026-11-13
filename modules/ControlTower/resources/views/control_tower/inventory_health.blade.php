<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
                Control Tower: Kesehatan Persediaan &amp; Aging Stok
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.inventory_health.export', array_filter($filters ?? []))" class="w-full sm:w-auto">
                    Ekspor CSV
                </x-ds.button>
                @if(in_array(auth()->user()?->role, ['admin', 'logistics_admin', 'planner'], true))
                    <form method="POST" action="{{ route('control_tower.inventory_health.refresh') }}" class="w-full sm:w-auto">
                        @csrf
                        <x-ds.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                            Analisis Ulang Stok
                        </x-ds.button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 space-y-4">
            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <x-ds.stat label="Modal Kerja Tertahan" :value="'Rp ' . number_format($totalTiedCapitalIdr, 0, ',', '.')" hint="Total nilai stok on-hand" icon="cube" />
                <x-ds.stat label="Eksposur Obsolet" :value="'Rp ' . number_format($totalObsolescenceRiskIdr, 0, ',', '.')" hint="Risiko dead/slow/excess/karantina" change-type="negative" />
                <x-ds.stat label="Dead & Slow Moving" :value="($statusCounts['DEAD_STOCK'] ?? 0) + ($statusCounts['SLOW_MOVING'] ?? 0)" hint="Dead: {{ $statusCounts['DEAD_STOCK'] ?? 0 }} | Slow: {{ $statusCounts['SLOW_MOVING'] ?? 0 }}" change-type="negative" />
                <x-ds.stat label="Healthy vs Shortage" :value="($statusCounts['HEALTHY'] ?? 0) . ' / ' . ($statusCounts['SHORTAGE_RISK'] ?? 0)" hint="Excess: {{ $statusCounts['EXCESS'] ?? 0 }}" icon="check-circle" />
            </div>

            <x-ds.card title="Distribusi Umur Stok (Aging Bucket)">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(['0-30', '31-90', '91-180', '>180'] as $bucket)
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 text-center">
                            <div class="text-xs font-medium text-slate-500">{{ $bucket }} Hari</div>
                            <div class="mt-1 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $bucketCounts[$bucket] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ds.card>

            <x-ds.card title="Filter Kesehatan Persediaan">
                <form method="GET" action="{{ route('control_tower.inventory_health.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                    <div>
                        <label for="health_status" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Status Kesehatan</label>
                        <select id="health_status" name="health_status" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Status</option>
                            @foreach(['DEAD_STOCK', 'SLOW_MOVING', 'EXCESS', 'SHORTAGE_RISK', 'HEALTHY'] as $st)
                                <option value="{{ $st }}" @selected(($filters['health_status'] ?? '') === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="aging_bucket" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Bucket Umur</label>
                        <select id="aging_bucket" name="aging_bucket" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Bucket</option>
                            @foreach(['0-30', '31-90', '91-180', '>180'] as $bk)
                                <option value="{{ $bk }}" @selected(($filters['aging_bucket'] ?? '') === $bk)>{{ $bk }} hari</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="echelon_node" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Node Eselon</label>
                        <select id="echelon_node" name="echelon_node" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Node</option>
                            @foreach($nodes as $node)
                                <option value="{{ $node }}" @selected(($filters['echelon_node'] ?? '') === $node)>{{ $node }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="snapshot_date" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Tanggal Snapshot</label>
                        <select id="snapshot_date" name="snapshot_date" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Tanggal</option>
                            @foreach($dates as $dt)
                                @php $dateStr = \Illuminate\Support\Carbon::parse($dt)->toDateString(); @endphp
                                <option value="{{ $dateStr }}" @selected(($filters['snapshot_date'] ?? '') === $dateStr)>{{ $dateStr }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <x-ds.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">Terapkan</x-ds.button>
                        <x-ds.button variant="ghost" size="sm" :href="route('control_tower.inventory_health.index')" class="w-full sm:w-auto">Reset</x-ds.button>
                    </div>
                </form>
            </x-ds.card>

            <x-ds.card title="Snapshot Kesehatan Persediaan Multi-Eselon">
                @if($snapshots->isEmpty())
                    <x-ds.empty
                        title="Belum ada snapshot kesehatan persediaan"
                        description="Klik 'Analisis Ulang Stok' untuk menghitung aging, DOS, dan eksposur modal kerja tertahan."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="py-2 pr-4">Item / Node</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Umur Lot</th>
                                    <th class="py-2 pr-4">On-Hand / Avail</th>
                                    <th class="py-2 pr-4">DOS</th>
                                    <th class="py-2 pr-4">Modal Tertahan</th>
                                    <th class="py-2 pr-4">Risiko Obsolet</th>
                                    <th class="py-2">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($snapshots as $snap)
                                    @php
                                        $badgeVariant = match($snap->health_status) {
                                            'HEALTHY' => 'success',
                                            'EXCESS' => 'info',
                                            'SLOW_MOVING', 'SHORTAGE_RISK' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="py-2.5 pr-4">
                                            <div class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $snap->item_code }}</div>
                                            <div class="text-xs text-slate-500">{{ $snap->echelon_node }}</div>
                                        </td>
                                        <td class="py-2.5 pr-4">
                                            <x-ds.badge :variant="$badgeVariant">{{ $snap->health_status }}</x-ds.badge>
                                        </td>
                                        <td class="py-2.5 pr-4 tabular-nums">
                                            <span class="font-medium">{{ $snap->aging_bucket }}</span>
                                            <span class="text-xs text-slate-500">({{ $snap->oldest_lot_age_days }}h)</span>
                                        </td>
                                        <td class="py-2.5 pr-4 tabular-nums">
                                            {{ number_format($snap->on_hand_qty) }} / {{ number_format($snap->available_qty) }}
                                            @if($snap->quarantine_qty > 0)
                                                <div class="text-xs text-amber-600">Karantina: {{ number_format($snap->quarantine_qty) }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-4 tabular-nums">{{ $snap->dos_days }}h</td>
                                        <td class="py-2.5 pr-4 tabular-nums">Rp {{ number_format($snap->tied_capital_idr, 0, ',', '.') }}</td>
                                        <td class="py-2.5 pr-4 tabular-nums font-medium text-red-700 dark:text-red-400">Rp {{ number_format($snap->obsolescence_risk_idr, 0, ',', '.') }}</td>
                                        <td class="py-2.5 font-mono text-xs">{{ $snap->snapshot_date?->toDateString() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
