<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Armada Robotika &amp; Kunci Zona</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Ringkasan Armada">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Total Unit</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $summary['total_units'] }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Aktif / Idle</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $summary['active_units'] }} / {{ $summary['idle_units'] }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Misi Aktif</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $summary['active_missions'] }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Kunci Zona Aktif</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $summary['active_zone_locks'] }}</p>
                    </div>
                </div>
            </x-ds.card>

            <x-ds.card title="Daftar Unit Robot">
                <div class="space-y-2">
                    @forelse($units as $unit)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm">
                            <span class="font-medium text-gray-900">{{ $unit->robot_code }} ({{ strtoupper((string) $unit->kind) }}) · Zona {{ $unit->current_zone_code ?? '—' }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-600">Baterai {{ $unit->battery_pct }}%</span>
                                <x-ds.badge :variant="$unit->status === 'faulted' ? 'danger' : ($unit->status === 'active' ? 'success' : 'neutral')">
                                    {{ $unit->status }}
                                </x-ds.badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada unit robot terdaftar.</p>
                    @endforelse
                </div>
            </x-ds.card>

            <x-ds.card title="Kunci Zona &amp; Safety Interlock Aktif">
                <div class="space-y-2">
                    @forelse($locks as $lock)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm">
                            <span>Gudang {{ $lock->warehouse_id }} · Zona {{ $lock->zone_code }} (Max {{ $lock->speed_limit_mm_s }} mm/s)</span>
                            <x-ds.badge :variant="$lock->interlock_active ? 'warning' : 'success'">
                                {{ $lock->interlock_active ? 'INTERLOCK MANUSIA' : 'AMAN' }}
                            </x-ds.badge>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Tidak ada kunci zona aktif saat ini.</p>
                    @endforelse
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

