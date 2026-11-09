<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Papan Kontrol Tempat Tidur Real-Time (Fase 87.3)
            </h2>
            <div class="flex gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    BOR: {{ number_format($borPercentage, 2) }}%
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {{ $occupancyCount }} / {{ $totalBeds }} Ranjang Terisi
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Panel Ringkasan Kapasitas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-sm font-medium text-gray-500">Total Tempat Tidur</div>
                <div class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $totalBeds }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-sm font-medium text-gray-500">Ranjang Terisi (Occupied)</div>
                <div class="text-2xl font-bold text-indigo-600 mt-1 tabular-nums">{{ $occupancyCount }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-sm font-medium text-gray-500">Ranjang Bebas (Available)</div>
                <div class="text-2xl font-bold text-emerald-600 mt-1 tabular-nums">{{ max(0, $totalBeds - $occupancyCount) }}</div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-sm font-medium text-gray-500">Antrean Admisi Rawat Inap</div>
                <div class="text-2xl font-bold text-amber-600 mt-1 tabular-nums">{{ $admissionsQueue->count() }}</div>
            </div>
        </div>

        <!-- Tabel Ranjang Real-Time -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Denah & Status Ranjang Bangsal</h3>
                    <div class="text-sm text-gray-500">Update Terakhir: <span class="font-mono">{{ now()->format('H:i:s') }} WIB</span></div>
                </div>

                @if($beds->isEmpty())
                    <div class="text-center py-12 text-gray-500">
                        <p>Belum ada data ranjang yang terdaftar.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Bed</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bangsal & Kamar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tarif/Hari (IDR)</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                @foreach($beds as $b)
                                    <tr>
                                        <td class="px-4 py-3 font-mono font-medium text-gray-900">{{ $b->bed_code }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $b->ward_name }} - R.{{ $b->room_number }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-800">
                                                {{ $b->bed_class }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono tabular-nums text-gray-900">
                                            Rp {{ number_format($b->rate_per_day_idr, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @php
                                                $badgeClass = match(strtoupper($b->status)) {
                                                    'AVAILABLE' => 'bg-emerald-100 text-emerald-800',
                                                    'OCCUPIED' => 'bg-indigo-100 text-indigo-800',
                                                    'RESERVED' => 'bg-amber-100 text-amber-800',
                                                    'CLEANING' => 'bg-blue-100 text-blue-800',
                                                    'ISOLATED_HOLD' => 'bg-purple-100 text-purple-800',
                                                    'MAINTENANCE' => 'bg-rose-100 text-rose-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                                                {{ strtoupper($b->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $beds->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

