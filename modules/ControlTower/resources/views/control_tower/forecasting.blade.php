<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Tower: Demand Forecasting &amp; Override</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Proyeksi Permintaan Multi-Model">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="py-2 pr-4">Kode</th>
                                <th class="py-2 pr-4">SKU</th>
                                <th class="py-2 pr-4">Periode</th>
                                <th class="py-2 pr-4">Model</th>
                                <th class="py-2 pr-4">Forecast</th>
                                <th class="py-2">Override</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($forecasts as $fc)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $fc->forecast_code }}</td>
                                    <td class="py-2 pr-4">{{ $fc->item_code }}</td>
                                    <td class="py-2 pr-4">{{ $fc->period }}</td>
                                    <td class="py-2 pr-4">{{ $fc->algorithm_model }}</td>
                                    <td class="py-2 pr-4">{{ $fc->forecast_qty }}</td>
                                    <td class="py-2">
                                        <x-ds.badge :variant="$fc->is_overridden ? 'warning' : 'neutral'">
                                            {{ $fc->is_overridden ? 'Override' : 'Sistem' }}
                                        </x-ds.badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-gray-500">Belum ada data forecast.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

