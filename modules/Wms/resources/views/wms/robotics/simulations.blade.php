<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Simulasi Lalu Lintas &amp; Throughput Robotika (Sandbox P3)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Hasil Snapshot Sandbox (Tanpa Mutasi Produksi)">
                <div class="mb-4 flex items-center gap-2">
                    <x-ds.badge variant="neutral">Sandbox P3: {{ $snapshot['handler'] }}</x-ds.badge>
                    <x-ds.badge variant="success">asumsi_simulasi</x-ds.badge>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm tabular-nums">
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Robot Online</p>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format((int) $snapshot['online_robots']) }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Total Zona</p>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format((int) $snapshot['total_zones']) }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Slot Simulasi</p>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format((int) $snapshot['simulated_zone_slots']) }}</p>
                    </div>
                    <div class="p-3 rounded border border-gray-200 bg-white">
                        <p class="text-gray-500">Potensi Deadlock</p>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format((int) $snapshot['simulated_deadlocks']) }}</p>
                    </div>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
