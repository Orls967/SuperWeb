<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Papan Gawat Darurat & Triase (ER Board - Fase 87.4)
        </h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Banner Code Blue Aktif jika ada -->
        @if($activeCodeBlue->isNotEmpty())
            <div class="bg-red-600 border border-red-700 text-white p-4 rounded-lg shadow-lg flex items-center justify-between animate-pulse">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl font-bold">⚠️ ALARM CODE BLUE AKTIF</span>
                    <span class="text-sm">Terdapat {{ $activeCodeBlue->count() }} kejadian resusitasi yang memerlukan respons segera!</span>
                </div>
                <a href="{{ route('hospital.critical_care.index') }}" class="px-4 py-2 bg-white text-red-700 font-bold rounded shadow hover:bg-gray-100 text-sm">
                    Lihat Monitor Kritis
                </a>
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Daftar Pasien Triase IGD Terkini</h3>

            @if($activeTriage->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <p>Tidak ada antrean gawat darurat aktif saat ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Waktu</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pasien / MRN</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">SpO2 (%)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Nadi (bpm)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Suhu (°C)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status Darurat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($activeTriage as $t)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-gray-600">{{ $t->recorded_at->format('H:i:s') }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ $t->encounter?->patient?->name ?? 'Pasien IGD' }}
                                        <div class="text-xs text-gray-400 font-mono">{{ $t->encounter?->patient?->mrn ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono tabular-nums font-semibold {{ $t->spo2_percent < 92 ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ number_format((float) $t->spo2_percent, 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono tabular-nums {{ ($t->heart_rate_bpm < 50 || $t->heart_rate_bpm > 110) ? 'text-red-600 font-bold' : 'text-gray-800' }}">
                                        {{ $t->heart_rate_bpm }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono tabular-nums text-gray-800">
                                        {{ number_format((float) $t->temp_c, 1) }}°C
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($t->code_blue_triggered)
                                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded bg-red-100 text-red-800">
                                                CODE BLUE
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded bg-emerald-100 text-emerald-800">
                                                STABIL
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

