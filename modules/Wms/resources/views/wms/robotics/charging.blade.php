<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jadwal Pengisian Daya Armada Robot</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Antrean Pengisian Daya (Minimum Armada Siaga)">
                <div class="space-y-2">
                    @forelse($schedules as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm gap-2">
                            <span class="font-medium text-gray-900">{{ $item->robot?->robot_code ?? "Robot #{$item->robot_id}" }} · Jendela [{{ $item->window_start_min }}, {{ $item->window_end_min }})</span>
                            <div class="flex items-center gap-3 tabular-nums">
                                <span class="text-gray-700">{{ $item->initial_battery_pct }}% &rarr; {{ $item->target_battery_pct }}%</span>
                                <x-ds.badge variant="neutral">{{ strtoupper((string) $item->status) }}</x-ds.badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada jadwal pengisian daya.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $schedules->links() }}</div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
