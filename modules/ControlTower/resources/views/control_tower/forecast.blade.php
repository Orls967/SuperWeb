<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                Control Tower: Seleksi Model &amp; Override Forecast Permintaan
            </h2>
            <div class="flex items-center gap-2">
                <x-ds.button :href="route('control_tower.forecast.export')" variant="secondary" size="sm">
                    Ekspor CSV
                </x-ds.button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 space-y-6">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-ds.stat
                    title="Total Forecast Aktif"
                    :value="$summary['total_forecasts'] ?? 0"
                    icon="chart-bar"
                    hint="Hasil seleksi aritmetika eksak I2"
                />
                <x-ds.stat
                    title="Forecast Di-Override"
                    :value="$summary['overridden_count'] ?? 0"
                    icon="cube"
                    hint="Penyesuaian planner teraudit"
                />
                <x-ds.stat
                    title="Menunggu Persetujuan"
                    :value="$summary['pending_approvals'] ?? 0"
                    icon="check-circle"
                    hint="Deviasi >= 2000 bps (20%)"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ds.card title="Seleksi Model Otomatis (SES / HOLT / CROSTON)" subtitle="Walk-forward backtest berbasis integer presisi penuh skala 10.000">
                    <form method="POST" action="{{ route('control_tower.forecast.select') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ds.input
                                name="item_code"
                                label="Kode SKU"
                                placeholder="SKU-CHIP-01"
                                required
                            />
                            <x-ds.input
                                name="period"
                                label="Periode (YYYY-MM)"
                                :value="now()->format('Y-m')"
                                placeholder="2026-11"
                                required
                            />
                        </div>
                        <x-ds.input
                            name="history"
                            label="Deret Historis Permintaan (pisahkan dengan koma)"
                            placeholder="100,120,140,160,180,200,220"
                            hint="Minimal 3 periode integer non-negatif."
                            required
                        />
                        <div class="flex justify-end">
                            <x-ds.button type="submit" variant="primary">
                                Jalankan Seleksi Model
                            </x-ds.button>
                        </div>
                    </form>
                </x-ds.card>

                <x-ds.card title="Planner Forecast Override (X53-4)" subtitle="Deviasi >= 2000 bps otomatis diajukan ke Approval Engine">
                    @php
                        $forecastOptions = $forecasts->mapWithKeys(
                            static fn ($fc) => [$fc->id => "{$fc->item_code} ({$fc->period}) - {$fc->algorithm_model}: {$fc->forecast_qty} unit"]
                        )->all();
                    @endphp
                    <form method="POST" action="{{ route('control_tower.forecast.override') }}" class="space-y-4">
                        @csrf
                        <x-ds.select
                            name="demand_forecast_id"
                            label="Pilih Forecast"
                            :options="$forecastOptions"
                            placeholder="-- Pilih data forecast --"
                            required
                        />
                        <x-ds.input
                            name="new_qty"
                            type="number"
                            min="0"
                            step="1"
                            label="Kuantitas Baru (Unit)"
                            placeholder="150"
                            required
                        />
                        <x-ds.input
                            name="reason"
                            label="Alasan Override (minimal 5 karakter)"
                            placeholder="Kenaikan kontrak distributor kuartal berjalan"
                            required
                        />
                        <div class="flex justify-end">
                            <x-ds.button type="submit" variant="primary">
                                Simpan Override
                            </x-ds.button>
                        </div>
                    </form>
                </x-ds.card>
            </div>

            @if($activeForecast && is_array($activeForecast->candidates_json) && count($activeForecast->candidates_json) > 0)
                <x-ds.card
                    title="Perbandingan Kandidat Model: {{ $activeForecast->item_code }} ({{ $activeForecast->period }})"
                    subtitle="Certificate SHA-256: {{ $activeForecast->certificate_hash ?? '-' }}"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                    <th class="py-2 pr-4">Model</th>
                                    <th class="py-2 pr-4">Forecast (Unit)</th>
                                    <th class="py-2 pr-4">MAE Scaled (x10.000)</th>
                                    <th class="py-2 pr-4">MAPE (bps)</th>
                                    <th class="py-2 pr-4">Titik Evaluasi</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach(['SES', 'HOLT', 'CROSTON'] as $modelKey)
                                    @php
                                        $cand = $activeForecast->candidates_json[$modelKey] ?? null;
                                        $isWinner = $activeForecast->algorithm_model === $modelKey;
                                    @endphp
                                    @if($cand)
                                        <tr>
                                            <td class="py-2 pr-4 font-semibold">{{ $cand['model'] ?? $modelKey }}</td>
                                            <td class="py-2 pr-4 tabular-nums">{{ $cand['forecast_qty'] ?? 0 }}</td>
                                            <td class="py-2 pr-4 tabular-nums font-mono">{{ $cand['mae_scaled'] ?? 0 }}</td>
                                            <td class="py-2 pr-4 tabular-nums">{{ $cand['mape_bps'] ?? 0 }} bps</td>
                                            <td class="py-2 pr-4 tabular-nums">{{ $cand['evaluation_points'] ?? 0 }}</td>
                                            <td class="py-2">
                                                <x-ds.badge :variant="$isWinner ? 'success' : 'neutral'">
                                                    {{ $isWinner ? 'Terpilih' : 'Kandidat' }}
                                                </x-ds.badge>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ds.card>
            @endif

            <x-ds.card title="Daftar Forecast Permintaan">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                <th class="py-2 pr-4">Kode</th>
                                <th class="py-2 pr-4">SKU</th>
                                <th class="py-2 pr-4">Periode</th>
                                <th class="py-2 pr-4">Model</th>
                                <th class="py-2 pr-4">Forecast</th>
                                <th class="py-2 pr-4">MAE Scaled</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($forecasts as $fc)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $fc->forecast_code }}</td>
                                    <td class="py-2 pr-4">{{ $fc->item_code }}</td>
                                    <td class="py-2 pr-4">{{ $fc->period }}</td>
                                    <td class="py-2 pr-4">{{ $fc->algorithm_model }}</td>
                                    <td class="py-2 pr-4 tabular-nums font-semibold">{{ $fc->forecast_qty }}</td>
                                    <td class="py-2 pr-4 tabular-nums font-mono">{{ $fc->mae_scaled ?? '-' }}</td>
                                    <td class="py-2 pr-4">
                                        <x-ds.badge :variant="$fc->is_overridden ? 'warning' : 'info'">
                                            {{ $fc->is_overridden ? 'Override' : 'Sistem' }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('control_tower.forecast.index', ['forecast_id' => $fc->id]) }}" class="text-xs font-medium text-blue-700 hover:underline">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 text-slate-500">Belum ada data forecast tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>

            <x-ds.card title="Audit Trail Override Forecast">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                <th class="py-2 pr-4">SKU / Periode</th>
                                <th class="py-2 pr-4">Baseline</th>
                                <th class="py-2 pr-4">Baru</th>
                                <th class="py-2 pr-4">Delta</th>
                                <th class="py-2 pr-4">Deviasi</th>
                                <th class="py-2 pr-4">Alasan</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Revert</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($overrides as $ov)
                                @php
                                    $ovStatus = $ov->status ?? 'applied';
                                    $badgeVariant = match ($ovStatus) {
                                        'applied' => 'success',
                                        'pending_approval' => 'warning',
                                        'rejected' => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td class="py-2 pr-4">{{ $ov->forecast?->item_code ?? '-' }} ({{ $ov->forecast?->period ?? '-' }})</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $ov->previous_qty }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $ov->new_qty }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $ov->delta_qty >= 0 ? '+' : '' }}{{ $ov->delta_qty }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $ov->deviation_bps ?? 0 }} bps</td>
                                    <td class="py-2 pr-4">{{ $ov->reason }}</td>
                                    <td class="py-2 pr-4">
                                        <x-ds.badge :variant="$badgeVariant">{{ $ovStatus }}</x-ds.badge>
                                    </td>
                                    <td class="py-2">
                                        @if($ovStatus === 'applied')
                                            <form method="POST" action="{{ route('control_tower.forecast.revert', $ov->id) }}">
                                                @csrf
                                                <x-ds.button type="submit" variant="secondary" size="sm">
                                                    Revert
                                                </x-ds.button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 text-slate-500">Belum ada riwayat override forecast.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
