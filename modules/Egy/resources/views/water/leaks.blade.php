<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Peta Kebocoran Pipa & Penugasan Work Order
                </h2>
                <p class="text-sm text-gray-500">Peringkat anomali kehilangan air fisik zona DMA dan integrasi Work Order pemeliharaan G7-K9</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('egy.water.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">
                    Kembali ke Ringkasan Air
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Daftar Zona Kebocoran Berperingkat Keparahan</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="leaks-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Waktu Terdeteksi</th>
                                <th class="px-6 py-3">Zona DMA</th>
                                <th class="px-6 py-3">Keparahan</th>
                                <th class="px-6 py-3">Inlet / Billed (L)</th>
                                <th class="px-6 py-3">Loss (%)</th>
                                <th class="px-6 py-3">Work Order (Asset)</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($incidents as $inc)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-mono">{{ $inc->detected_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $inc->zone_code }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full {{ $inc->severity === 'CRITICAL' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $inc->severity }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        {{ number_format($inc->inlet_volume_l) }} L / {{ number_format($inc->billed_volume_l) }} L
                                    </td>
                                    <td class="px-6 py-4 font-bold text-rose-600">
                                        {{ number_format($inc->loss_percentage_bps / 100, 1) }}%
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($inc->asset_work_order_id)
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-mono font-semibold rounded">
                                                WO #{{ $inc->asset_work_order_id }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 uppercase">
                                            {{ str_replace('_', ' ', $inc->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">Tidak ada insiden kebocoran pipa yang aktif</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
