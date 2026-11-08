<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Armada Maritim & Manajemen Kapal Niaga') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('port.voyages.index') }}" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Pelayaran (Voyages)</a>
                <a href="{{ route('port.charter.index') }}" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Perjanjian Sewa (Charter)</a>
                <a href="{{ route('port.services.index') }}" class="px-3 py-1 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700">Layanan Pelabuhan (PDA/FDA)</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Ringkasan Statistik Armada -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-indigo-500">
                    <p class="text-xs uppercase text-gray-500 font-semibold">Total Kapal Terdaftar</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $vessels->count() }} Unit</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-emerald-500">
                    <p class="text-xs uppercase text-gray-500 font-semibold">Kapal Aktif Beroperasi</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ $vessels->where('operational_status', 'IN_SERVICE')->count() }} Unit</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-blue-500">
                    <p class="text-xs uppercase text-gray-500 font-semibold">Pelayaran Berlangsung</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $activeVoyages->whereIn('status', ['BALLAST_PASSAGE', 'LADEN_PASSAGE', 'COMMENCED_LOADING'])->count() }}</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-amber-500">
                    <p class="text-xs uppercase text-gray-500 font-semibold">Rating CII Rata-Rata</p>
                    <p class="text-2xl font-bold text-amber-600">B (Superior)</p>
                </div>
            </div>

            <!-- Tabel Daftar Kapal Niaga -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Kapal Niaga & Evaluasi Intensitas Karbon (CII)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">IMO / Nama Kapal</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Tipe / Bendera</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">DWT (MT)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">LOA / Draft (m)</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Klasifikasi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Rating CII (IMO)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($vessels as $vessel)
                                @php
                                    $cii = $ciiSummaries[$vessel->id]['evaluation'] ?? null;
                                    $rating = $cii['rating'] ?? 'C';
                                    $badgeColor = match($rating) {
                                        'A' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'B' => 'bg-blue-100 text-blue-800 border-blue-300',
                                        'C' => 'bg-amber-100 text-amber-800 border-amber-300',
                                        'D' => 'bg-orange-100 text-orange-800 border-orange-300',
                                        default => 'bg-red-100 text-red-800 border-red-300'
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ $vessel->vessel_name }}
                                        <span class="block text-xs text-gray-400 font-normal">IMO: {{ $vessel->imo_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ str_replace('_', ' ', $vessel->vessel_type) }}
                                        <span class="block text-xs text-gray-400">Flag: {{ $vessel->flag_state }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-800">{{ number_format($vessel->deadweight_tonnage) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600">
                                        {{ number_format($vessel->length_overall_cm / 100, 1) }}m / {{ number_format($vessel->design_draft_cm / 100, 1) }}m
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $vessel->classification->code ?? 'BKI' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 rounded text-xs font-bold border {{ $badgeColor }}">
                                            Rating {{ $rating }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700">
                                            {{ $vessel->operational_status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada armada kapal yang terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

