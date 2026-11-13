<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Log Insiden Keselamatan Robotika (Simulasi)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Daftar Insiden Append-Only (asumsi_simulasi)">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <x-ds.badge variant="warning">Simulasi — Tanpa Klaim Kepatuhan</x-ds.badge>
                    <a href="{{ route('wms.robotics.export', ['entity' => 'incidents']) }}" class="text-indigo-600 underline">Ekspor CSV Insiden</a>
                </div>
                <div class="space-y-2">
                    @forelse($incidents as $incident)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm gap-2">
                            <div>
                                <p class="font-medium text-gray-900">{{ $incident->incident_code }} · {{ $incident->incident_type }}</p>
                                <p class="text-xs text-gray-600">{{ $incident->description }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ds.badge :variant="$incident->severity === 'high' ? 'warning' : 'neutral'">{{ strtoupper((string) $incident->severity) }}</x-ds.badge>
                                <x-ds.badge :variant="$incident->status === 'closed' ? 'success' : 'neutral'">{{ strtoupper((string) $incident->status) }}</x-ds.badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada insiden keselamatan tercatat.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $incidents->links() }}</div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

