<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    Control Tower: Skor Risiko Pesanan &amp; Pasokan
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Skor deterministik 0–1000 (Shortfall 400 · Lead Time 250 · Umur Lot 200 · In-Transit 150)
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.alerts.index')">
                    Radar Gangguan
                </x-ds.button>
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.risk.export')">
                    Ekspor CSV
                </x-ds.button>
                @can('manageAlerts', \Modules\ControlTower\Domain\Models\SopCycle::class)
                    <form method="POST" action="{{ route('control_tower.risk.scan') }}" class="inline">
                        @csrf
                        <x-ds.button type="submit" size="sm">
                            Pindai Risiko Sekarang
                        </x-ds.button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 space-y-5">
            @if(session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/30 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ds.stat title="Total Item/Order Dinilai" :value="$summary['total_scored']" icon="cube" />
                <x-ds.stat title="Risiko CRITICAL" :value="$summary['critical_count']" delta-direction="{{ $summary['critical_count'] > 0 ? 'down' : 'neutral' }}" icon="chart-bar" />
                <x-ds.stat title="Risiko HIGH" :value="$summary['high_count']" icon="chart-bar" />
                <x-ds.stat title="Rata-Rata Skor Risiko" :value="$summary['avg_score'] . ' / 1000'" icon="check-circle" />
            </div>

            <x-ds.card title="Filter &amp; Pemindaian Target">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <form method="GET" action="{{ route('control_tower.risk.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                        <x-ds.input name="item" label="Cari SKU / Item" :value="$item" placeholder="Mis. VALVE-OIL-01" />
                        <x-ds.select
                            name="tier"
                            label="Tier Risiko"
                            placeholder="Semua Tier"
                            :selected="$tier"
                            :options="['CRITICAL' => 'CRITICAL', 'HIGH' => 'HIGH', 'MEDIUM' => 'MEDIUM', 'LOW' => 'LOW']"
                        />
                        <div class="flex gap-2">
                            <x-ds.button type="submit" variant="secondary" size="md">Filter</x-ds.button>
                            <x-ds.button variant="ghost" size="md" :href="route('control_tower.risk.index')">Reset</x-ds.button>
                        </div>
                    </form>

                    @can('manageAlerts', \Modules\ControlTower\Domain\Models\SopCycle::class)
                        <form method="POST" action="{{ route('control_tower.risk.scan') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                            @csrf
                            <x-ds.input name="item_code" label="Scan Item Spesifik (Opsional)" placeholder="Kosongkan untuk semua item" />
                            <x-ds.button type="submit" size="md">Jalankan Scan</x-ds.button>
                        </form>
                    @endcan
                </div>
            </x-ds.card>

            <x-ds.card title="Daftar Skor Risiko">
                @if($scores->isEmpty())
                    <x-ds.empty
                        title="Belum ada skor risiko tercatat"
                        description="Jalankan pemindaian risiko (tower:risk-scan) untuk menghitung skor 4 faktor per item dan order."
                    />
                @else
                    {{-- Desktop Table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-slate-500 uppercase">
                                    <th class="py-2.5 pr-4">Item</th>
                                    <th class="py-2.5 pr-4">Referensi Order</th>
                                    <th class="py-2.5 pr-4">Tier</th>
                                    <th class="py-2.5 pr-4 text-right">Skor</th>
                                    <th class="py-2.5 pr-4 text-right">Shortfall / LT / Lot / Transit</th>
                                    <th class="py-2.5">Rekomendasi Mitigasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($scores as $score)
                                    @php
                                        $badgeVariant = match($score->risk_tier) {
                                            'CRITICAL' => 'danger',
                                            'HIGH' => 'warning',
                                            'MEDIUM' => 'info',
                                            default => 'success',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="py-3 pr-4 font-semibold text-slate-900 dark:text-slate-100">{{ $score->item_code }}</td>
                                        <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $score->order_reference ?? 'ITEM-LEVEL' }}</td>
                                        <td class="py-3 pr-4">
                                            <x-ds.badge :variant="$badgeVariant">{{ $score->risk_tier }}</x-ds.badge>
                                        </td>
                                        <td class="py-3 pr-4 text-right font-mono tabular-nums font-bold">{{ $score->risk_score }}</td>
                                        <td class="py-3 pr-4 text-right font-mono tabular-nums text-xs text-slate-600 dark:text-slate-300">
                                            {{ $score->shortfall_component }} / {{ $score->lead_time_component }} / {{ $score->shelf_life_component }} / {{ $score->transit_component }}
                                        </td>
                                        <td class="py-3 text-xs text-slate-600 dark:text-slate-300 max-w-md">
                                            {{ $score->mitigation_recommendation }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile 375px Card List --}}
                    <div class="block md:hidden space-y-3">
                        @foreach($scores as $score)
                            @php
                                $badgeVariant = match($score->risk_tier) {
                                    'CRITICAL' => 'danger',
                                    'HIGH' => 'warning',
                                    'MEDIUM' => 'info',
                                    default => 'success',
                                };
                            @endphp
                            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="font-semibold text-sm text-slate-900 dark:text-slate-100">{{ $score->item_code }}</div>
                                        <div class="text-xs text-slate-500">{{ $score->order_reference ?? 'ITEM-LEVEL' }}</div>
                                    </div>
                                    <x-ds.badge :variant="$badgeVariant">{{ $score->risk_tier }} · {{ $score->risk_score }}</x-ds.badge>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <div>Shortfall: <span class="font-mono font-semibold">{{ $score->shortfall_component }}/400</span></div>
                                    <div>Lead Time: <span class="font-mono font-semibold">{{ $score->lead_time_component }}/250</span></div>
                                    <div>Umur Lot: <span class="font-mono font-semibold">{{ $score->shelf_life_component }}/200</span></div>
                                    <div>In-Transit: <span class="font-mono font-semibold">{{ $score->transit_component }}/150</span></div>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 pt-1">
                                    {{ $score->mitigation_recommendation }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if($scores->hasPages())
                        <div class="pt-4">{{ $scores->links() }}</div>
                    @endif
                @endif
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
