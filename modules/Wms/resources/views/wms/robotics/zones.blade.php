<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Zona Lalu Lintas &amp; Reservasi Interval J1</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Zona Lalu Lintas (Simulasi)">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <x-ds.badge variant="neutral">Mode: Simulasi Perangkat</x-ds.badge>
                    <a href="{{ route('wms.robotics.export', ['entity' => 'reservations']) }}" class="text-indigo-600 underline">Ekspor CSV Reservasi</a>
                </div>
                <div class="space-y-2">
                    @forelse($zones as $zone)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm gap-2">
                            <span class="font-medium text-gray-900">{{ $zone->zone_code }} ({{ $zone->zone_kind }})</span>
                            <div class="flex items-center gap-3 tabular-nums">
                                <span class="text-gray-700">Kapasitas k: {{ number_format((int) $zone->capacity_k) }}</span>
                                <x-ds.badge :variant="$zone->staff_blocked ? 'warning' : 'success'">
                                    {{ $zone->staff_blocked ? 'Diblokir Staf (Interlock)' : 'Terbuka' }}
                                </x-ds.badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada data zona lalu lintas.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $zones->links() }}</div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

