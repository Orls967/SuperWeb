<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shop Floor Execution &amp; Work Order Progress</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Pelaksanaan Lantai Produksi (Shop Floor Control)</h3>
                <p class="text-sm text-gray-500 mb-6">Pencatatan real-time kuantitas selesai, scrap, jam kerja operator, jam mesin, dan pergerakan status work order.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-teal-50 border border-teal-100 rounded-lg">
                        <span class="text-xs font-semibold text-teal-600 uppercase">Work Order Berjalan</span>
                        <p class="text-2xl font-black text-teal-900 mt-1">In Progress</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">First Pass Yield</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">98.2%</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Toleransi Scrap</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">&lt; 3.0% Max</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Tracking Lot</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">Barcoded</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    Setiap konfirmasi output langsung mengonsumsi alokasi material WIP dan membukukan jam kerja buruh langsung ke modul HCM &amp; Biaya Manufaktur.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
