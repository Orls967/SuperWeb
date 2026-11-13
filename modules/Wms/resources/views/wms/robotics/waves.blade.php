<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wave Planning Robotika FFD (J2)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Daftar Wave per Carrier &amp; Cutoff">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <x-ds.badge variant="neutral">Algoritma: First-Fit Decreasing (J2)</x-ds.badge>
                    <a href="{{ route('wms.robotics.export', ['entity' => 'waves']) }}" class="text-indigo-600 underline">Ekspor CSV Wave</a>
                </div>
                <div class="space-y-2">
                    @forelse($waves as $wave)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm gap-2">
                            <span class="font-medium text-gray-900">{{ $wave->wave_code }} · {{ $wave->carrier_code }} (Cutoff {{ $wave->cutoff_min }})</span>
                            <div class="flex items-center gap-3 tabular-nums">
                                <span class="text-gray-700">{{ number_format((int) $wave->allocated_load) }} / {{ number_format((int) $wave->capacity) }}</span>
                                <x-ds.badge :variant="$wave->is_oversize ? 'warning' : 'success'">{{ strtoupper((string) $wave->status) }}</x-ds.badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada wave robotika.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $waves->links() }}</div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
