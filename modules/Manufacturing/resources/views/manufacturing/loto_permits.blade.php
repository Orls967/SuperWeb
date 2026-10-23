<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">HSE Safety &amp; Lockout/Tagout (LOTO) Permits</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Izin Kerja Berisiko &amp; Keselamatan Mesin (LOTO)</h3>
                <p class="text-sm text-gray-500 mb-6">Pemberian izin kerja pemeliharaan berisiko tinggi (isolasi energi listrik/mekanis, hot work, confined space) dengan sign-off keselamatan ketat.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Protokol LOTO</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">Zero Energy State</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Kepatuhan HSE</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">100% Signed</p>
                    </div>
                    <div class="p-4 bg-rose-50 border border-rose-100 rounded-lg">
                        <span class="text-xs font-semibold text-rose-600 uppercase">Masa Berlaku</span>
                        <p class="text-2xl font-black text-rose-900 mt-1">Time-Bounded</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Audit Trail</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">Cryptographic Log</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    Setiap intervensi perbaikan mesin produksi wajib mengaktifkan izin LOTO aktif sebelum teknisi mekanik/listrik dapat memulai pekerjaan fisik.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
