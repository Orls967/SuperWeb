<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard Efisiensi Ranjang Barber-Johnson (Fase 87.6)
            </h2>
            <a href="?csv=1" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700 shadow-sm flex items-center gap-1">
                <span>📥 Ekspor CSV</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Empat Metrik Barber-Johnson -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-xs font-semibold text-gray-400 uppercase">BOR (Bed Occupancy Rate)</div>
                <div class="text-3xl font-extrabold text-indigo-600 mt-2 tabular-nums">
                    {{ sprintf('%.2f', $bjResult->borPercentage()) }}%
                </div>
                <div class="text-xs text-gray-500 mt-1">Standar: 75.00% – 85.00%</div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-xs font-semibold text-gray-400 uppercase">ALOS (Length of Stay)</div>
                <div class="text-3xl font-extrabold text-emerald-600 mt-2 tabular-nums">
                    {{ sprintf('%.2f', $bjResult->alosDays()) }} <span class="text-sm font-normal text-gray-500">hari</span>
                </div>
                <div class="text-xs text-gray-500 mt-1">Standar: 3.00 – 9.00 hari</div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-xs font-semibold text-gray-400 uppercase">TOI (Turn Over Interval)</div>
                <div class="text-3xl font-extrabold text-amber-600 mt-2 tabular-nums">
                    {{ sprintf('%.2f', $bjResult->toiDays()) }} <span class="text-sm font-normal text-gray-500">hari</span>
                </div>
                <div class="text-xs text-gray-500 mt-1">Standar: 1.00 – 3.00 hari</div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="text-xs font-semibold text-gray-400 uppercase">BTO (Bed Turn Over)</div>
                <div class="text-3xl font-extrabold text-purple-600 mt-2 tabular-nums">
                    {{ sprintf('%.2f', $bjResult->btoTurns()) }} <span class="text-sm font-normal text-gray-500">kali</span>
                </div>
                <div class="text-xs text-gray-500 mt-1">Standar: ≥ 30 kali/tahun</div>
            </div>
        </div>

        <!-- Analisis Kuadran Barber-Johnson -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-2">Analisis Kuadran Efisiensi Operasional</h3>
            <p class="text-sm text-gray-600 mb-6">
                Berdasarkan grafik Barber-Johnson, hubungan antara BOR, ALOS, TOI, dan BTO mencerminkan utilisasi kapasitas medis dan waktu perputaran ranjang.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border rounded-lg p-4 bg-gray-50">
                    <h4 class="font-bold text-gray-800 text-sm mb-2">Parameter Periode Analisis</h4>
                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <dt class="text-gray-500">Total Tempat Tidur Operasional:</dt>
                        <dd class="font-semibold text-gray-800 text-right tabular-nums">{{ $totalBeds }} bed</dd>

                        <dt class="text-gray-500">Periode Hari Pengamatan:</dt>
                        <dd class="font-semibold text-gray-800 text-right tabular-nums">30 hari</dd>

                        <dt class="text-gray-500">Total Hari Rawat Inap:</dt>
                        <dd class="font-semibold text-gray-800 text-right tabular-nums">{{ number_format($inpatientDays) }} hari</dd>

                        <dt class="text-gray-500">Total Pasien Keluar:</dt>
                        <dd class="font-semibold text-gray-800 text-right tabular-nums">{{ number_format($discharges) }} pasien</dd>
                    </dl>
                </div>

                <div class="border rounded-lg p-4 bg-gray-50 flex flex-col justify-center">
                    <h4 class="font-bold text-gray-800 text-sm mb-1">Status Utilisasi</h4>
                    @if($bjResult->borPercentage() >= 75.0 && $bjResult->borPercentage() <= 85.0)
                        <div class="text-emerald-700 font-bold text-sm">
                            ✓ Utilisasi Berada Pada Zona Efisiensi Ideal (75%–85%)
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Kapasitas ranjang optimal tanpa risiko kelebihan beban staf medis.</p>
                    @elseif($bjResult->borPercentage() > 85.0)
                        <div class="text-red-700 font-bold text-sm">
                            ⚠️ Overcapacity Alert (BOR > 85%)
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Risiko penumpukan pasien di IGD dan penurunan mutu pelayanan.</p>
                    @else
                        <div class="text-amber-700 font-bold text-sm">
                            ℹ️ Underutilization (BOR < 75%)
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Kapasitas ranjang masih tersedia untuk menerima rujukan atau kasus elektif.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
