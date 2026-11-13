<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    Control Tower: Radar Gangguan &amp; Persetujuan Mitigasi
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Deduplikasi idempoten (echelon, overdue transit, risk CRITICAL/HIGH) &amp; alur persetujuan realokasi
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.risk.index')">
                    Skor Risiko
                </x-ds.button>
                <x-ds.button variant="secondary" size="sm" :href="route('control_tower.alerts.export')">
                    Ekspor CSV
                </x-ds.button>
                @can('manageAlerts', \Modules\ControlTower\Domain\Models\SopCycle::class)
                    <form method="POST" action="{{ route('control_tower.alerts.scan') }}" class="inline">
                        @csrf
                        <x-ds.button type="submit" size="sm">
                            Pindai Gangguan Sekarang
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

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ds.stat title="Peringatan Aktif" :value="$summary['active_count']" delta-direction="{{ $summary['active_count'] > 0 ? 'down' : 'neutral' }}" icon="chart-bar" />
                <x-ds.stat title="Aktif CRITICAL" :value="$summary['critical_count']" icon="cube" />
                <x-ds.stat title="Sudah Dimitigasi" :value="$summary['mitigated_count']" icon="check-circle" />
                <x-ds.stat title="Aturan Alert Aktif" :value="$summary['rules_count']" icon="check-circle" />
            </div>

            <x-ds.card title="Filter Peringatan">
                <form method="GET" action="{{ route('control_tower.alerts.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    <x-ds.select
                        name="status"
                        label="Status"
                        placeholder="Semua Status"
                        :selected="$status"
                        :options="['active' => 'Active', 'mitigated' => 'Mitigated', 'resolved' => 'Resolved']"
                    />
                    <x-ds.select
                        name="severity"
                        label="Keparahan"
                        placeholder="Semua Keparahan"
                        :selected="$severity"
                        :options="['CRITICAL' => 'CRITICAL', 'HIGH' => 'HIGH', 'MEDIUM' => 'MEDIUM', 'LOW' => 'LOW']"
                    />
                    <div class="flex gap-2">
                        <x-ds.button type="submit" variant="secondary" size="md">Filter</x-ds.button>
                        <x-ds.button variant="ghost" size="md" :href="route('control_tower.alerts.index')">Reset</x-ds.button>
                    </div>
                </form>
            </x-ds.card>

            <x-ds.card title="Daftar Peringatan Gangguan">
                @if($alerts->isEmpty())
                    <x-ds.empty
                        title="Tidak ada peringatan gangguan"
                        description="Jalankan pemindaian gangguan (tower:alerts-scan) untuk mendeteksi bottleneck stok, keterlambatan transit, atau risiko tinggi."
                    />
                @else
                    {{-- Desktop Table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-slate-500 uppercase">
                                    <th class="py-2.5 pr-4">Kode</th>
                                    <th class="py-2.5 pr-4">Keparahan</th>
                                    <th class="py-2.5 pr-4">Kategori &amp; Judul</th>
                                    <th class="py-2.5 pr-4">Status</th>
                                    <th class="py-2.5 pr-4">Usulan Mitigasi</th>
                                    <th class="py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($alerts as $alert)
                                    @php
                                        $sevVariant = match($alert->severity) {
                                            'CRITICAL' => 'danger',
                                            'HIGH' => 'warning',
                                            'MEDIUM' => 'info',
                                            default => 'neutral',
                                        };
                                        $statusVariant = match($alert->status) {
                                            'active' => 'warning',
                                            'mitigated' => 'success',
                                            default => 'neutral',
                                        };
                                        $proposal = is_array($alert->mitigation_proposal_json) ? $alert->mitigation_proposal_json : [];
                                    @endphp
                                    <tr>
                                        <td class="py-3 pr-4 font-mono text-xs font-semibold">{{ $alert->alert_code }}</td>
                                        <td class="py-3 pr-4">
                                            <x-ds.badge :variant="$sevVariant">{{ $alert->severity }}</x-ds.badge>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $alert->title }}</div>
                                            <div class="text-xs text-slate-500">{{ $alert->category }} · {{ $alert->description }}</div>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <x-ds.badge :variant="$statusVariant">{{ strtoupper($alert->status) }}</x-ds.badge>
                                        </td>
                                        <td class="py-3 pr-4 text-xs text-slate-600 dark:text-slate-300">
                                            @if(($proposal['action'] ?? null) === 'reallocation')
                                                Realokasi {{ $proposal['transfer_qty'] ?? 0 }} unit ({{ $proposal['from_node'] ?? '-' }} → {{ $proposal['to_node'] ?? '-' }})
                                            @elseif(!empty($proposal))
                                                {{ $proposal['action'] ?? 'manual' }} ({{ $proposal['status'] ?? 'pending' }})
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($alert->status === 'active')
                                                @can('manageAlerts', \Modules\ControlTower\Domain\Models\SopCycle::class)
                                                    <form method="POST" action="{{ route('control_tower.alerts.mitigate', $alert->id) }}" class="inline">
                                                        @csrf
                                                        <x-ds.button type="submit" size="sm" variant="primary">
                                                            Setujui &amp; Mitigasi
                                                        </x-ds.button>
                                                    </form>
                                                @endcan
                                            @else
                                                <span class="text-xs text-slate-400">Selesai</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile 375px Card List --}}
                    <div class="block md:hidden space-y-3">
                        @foreach($alerts as $alert)
                            @php
                                $sevVariant = match($alert->severity) {
                                    'CRITICAL' => 'danger',
                                    'HIGH' => 'warning',
                                    'MEDIUM' => 'info',
                                    default => 'neutral',
                                };
                                $statusVariant = match($alert->status) {
                                    'active' => 'warning',
                                    'mitigated' => 'success',
                                    default => 'neutral',
                                };
                                $proposal = is_array($alert->mitigation_proposal_json) ? $alert->mitigation_proposal_json : [];
                            @endphp
                            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold">{{ $alert->alert_code }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <x-ds.badge :variant="$sevVariant">{{ $alert->severity }}</x-ds.badge>
                                        <x-ds.badge :variant="$statusVariant">{{ strtoupper($alert->status) }}</x-ds.badge>
                                    </div>
                                </div>
                                <div class="font-medium text-sm text-slate-900 dark:text-slate-100">{{ $alert->title }}</div>
                                <p class="text-xs text-slate-500">{{ $alert->description }}</p>
                                @if(($proposal['action'] ?? null) === 'reallocation')
                                    <div class="text-xs rounded-lg bg-slate-50 dark:bg-slate-900/50 p-2">
                                        Usulan: Realokasi {{ $proposal['transfer_qty'] ?? 0 }} unit ({{ $proposal['from_node'] ?? '-' }} → {{ $proposal['to_node'] ?? '-' }})
                                    </div>
                                @endif
                                @if($alert->status === 'active')
                                    @can('manageAlerts', \Modules\ControlTower\Domain\Models\SopCycle::class)
                                        <form method="POST" action="{{ route('control_tower.alerts.mitigate', $alert->id) }}" class="pt-1">
                                            @csrf
                                            <x-ds.button type="submit" size="sm" class="w-full">
                                                Setujui &amp; Mitigasi
                                            </x-ds.button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($alerts->hasPages())
                        <div class="pt-4">{{ $alerts->links() }}</div>
                    @endif
                @endif
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
