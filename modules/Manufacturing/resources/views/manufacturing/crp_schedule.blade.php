<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">CRP Finite Capacity Work Center Scheduling</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Penjadwalan Kapasitas Terbatas (Capacity Requirements Planning)</h3>
                <p class="text-sm text-gray-500 mb-6">Pemantauan beban menit kerja pada stasiun kerja, deteksi bottleneck kelebihan kapasitas, dan penyeimbangan lini produksi.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">Stasiun Kerja Aktif</span>
                        <p class="text-2xl font-black text-purple-900 mt-1">12 Work Centers</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Utilisasi Rata-rata</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">84.6%</p>
                    </div>
                    <div class="p-4 bg-rose-50 border border-rose-100 rounded-lg">
                        <span class="text-xs font-semibold text-rose-600 uppercase">Bottleneck Terdeteksi</span>
                        <p class="text-2xl font-black text-rose-900 mt-1">0 Mesin Kritis</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    Sistem Finite Capacity menjadwalkan pekerjaan ke slot jam yang tersedia secara berurutan, menghindari penumpukan antrean WIP di lantai pabrik.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
