<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Misi Robotik &amp; Fallback Manual</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Antrean &amp; Eksekusi Misi Robot">
                <div class="space-y-2">
                    @forelse($missions as $mission)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $mission->mission_code }} · Zona {{ $mission->zone_code }}</p>
                                <p class="text-xs text-gray-600">Robot #{{ $mission->robot_unit_id }} · Fallback: {{ $mission->fallback_manual ? 'YA' : 'TIDAK' }}</p>
                            </div>
                            <x-ds.badge :variant="$mission->status === 'completed' ? 'success' : ($mission->status === 'failed_fallback' || $mission->status === 'interlocked' ? 'warning' : 'neutral')">
                                {{ $mission->status }}
                            </x-ds.badge>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada misi robotik tercatat.</p>
                    @endforelse
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

