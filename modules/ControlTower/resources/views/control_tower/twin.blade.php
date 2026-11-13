<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Tower: What-If Digital Twin</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Skenario Simulasi Sandbox">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="py-2 pr-4">Kode</th>
                                <th class="py-2 pr-4">Nama Skenario</th>
                                <th class="py-2 pr-4">Jenis Kejutan</th>
                                <th class="py-2 pr-4">OTIF Simulasi (bps)</th>
                                <th class="py-2">Order Berisiko</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($scenarios as $sc)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $sc->scenario_code }}</td>
                                    <td class="py-2 pr-4">{{ $sc->name }}</td>
                                    <td class="py-2 pr-4">{{ $sc->shock_type }}</td>
                                    <td class="py-2 pr-4">{{ $sc->simulated_otif_bps }}</td>
                                    <td class="py-2">{{ $sc->at_risk_orders_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-500">Belum ada skenario simulasi twin.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

