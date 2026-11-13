<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
                Control Tower: Kartu Skor Pemasok
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.suppliers.export', array_filter($filters ?? []))" class="w-full sm:w-auto">
                    Ekspor CSV
                </x-ds.button>
                @if(in_array(auth()->user()?->role, ['admin', 'logistics_admin', 'planner'], true))
                    <form method="POST" action="{{ route('control_tower.suppliers.refresh') }}" class="w-full sm:w-auto">
                        @csrf
                        <x-ds.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                            Hitung Ulang Skor
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
                <x-ds.stat label="Total Pemasok" :value="$totalCount" hint="Periode terpilih" icon="cube" />
                <x-ds.stat label="Rata-rata Skor" :value="$avgCompositeScore . ' / 1000'" hint="Skor komposit" icon="chart-bar" />
                <x-ds.stat label="Strategic & Preferred" :value="($tierCounts['STRATEGIC'] ?? 0) + ($tierCounts['PREFERRED'] ?? 0)" hint="Skor >= 700" icon="check-circle" change-type="positive" />
                <x-ds.stat label="Watchlist & At Risk" :value="($tierCounts['WATCHLIST'] ?? 0) + ($tierCounts['AT_RISK'] ?? 0)" hint="Skor < 700" change-type="negative" />
            </div>

            <x-ds.card title="Filter Kartu Skor">
                <form method="GET" action="{{ route('control_tower.suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <label for="search" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Cari Pemasok</label>
                        <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Kode / nama pemasok" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm" />
                    </div>
                    <div>
                        <label for="tier" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Tier</label>
                        <select id="tier" name="tier" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Tier</option>
                            @foreach(['STRATEGIC', 'PREFERRED', 'WATCHLIST', 'AT_RISK'] as $tierOpt)
                                <option value="{{ $tierOpt }}" @selected(($filters['tier'] ?? '') === $tierOpt)>{{ $tierOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="period" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Periode</label>
                        <select id="period" name="period" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                            <option value="">Semua Periode</option>
                            @foreach($periods as $p)
                                <option value="{{ $p }}" @selected(($filters['period'] ?? '') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <x-ds.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">Terapkan</x-ds.button>
                        <x-ds.button variant="ghost" size="sm" :href="route('control_tower.suppliers.index')" class="w-full sm:w-auto">Reset</x-ds.button>
                    </div>
                </form>
            </x-ds.card>

            <x-ds.card title="Daftar Kartu Skor Pemasok (OTD 45% • Konsistensi 30% • Sampel 25%)">
                @if($scorecards->isEmpty())
                    <x-ds.empty
                        title="Belum ada snapshot kartu skor pemasok"
                        description="Jalankan perhitungan skor atau perintah tower:supplier-scorecard untuk menghasilkan kartu skor."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="py-2 pr-4">Pemasok</th>
                                    <th class="py-2 pr-4">Periode</th>
                                    <th class="py-2 pr-4">Skor</th>
                                    <th class="py-2 pr-4">Tier</th>
                                    <th class="py-2 pr-4">OTD (‰)</th>
                                    <th class="py-2 pr-4">Lead Time (μ / σ)</th>
                                    <th class="py-2 pr-4">Sampel</th>
                                    <th class="py-2">Rekomendasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($scorecards as $card)
                                    @php
                                        $badgeVariant = match($card->tier) {
                                            'STRATEGIC' => 'success',
                                            'PREFERRED' => 'info',
                                            'WATCHLIST' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="py-2.5 pr-4">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $card->supplier_name }}</div>
                                            <div class="text-xs text-slate-500 font-mono">{{ $card->supplier_ref }}</div>
                                        </td>
                                        <td class="py-2.5 pr-4 font-mono text-xs">{{ $card->period }}</td>
                                        <td class="py-2.5 pr-4 font-bold tabular-nums">{{ $card->composite_score }}</td>
                                        <td class="py-2.5 pr-4">
                                            <x-ds.badge :variant="$badgeVariant">{{ $card->tier }}</x-ds.badge>
                                        </td>
                                        <td class="py-2.5 pr-4 tabular-nums">{{ $card->on_time_permil }}</td>
                                        <td class="py-2.5 pr-4 tabular-nums">{{ $card->mean_lead_time_days }}h / ±{{ $card->sigma_lead_time_days }}h</td>
                                        <td class="py-2.5 pr-4 tabular-nums">{{ $card->samples_count }}</td>
                                        <td class="py-2.5 text-xs text-slate-600 dark:text-slate-300 max-w-xs">{{ $card->action_recommendation }}</td>
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
