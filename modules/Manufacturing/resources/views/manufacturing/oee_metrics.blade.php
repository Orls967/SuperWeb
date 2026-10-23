<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Overall Equipment Effectiveness (OEE) &amp; TPM</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Metrik OEE &amp; Pemeliharaan Produktif Total (TPM)</h3>
                <p class="text-sm text-gray-500 mb-6">Pengukuran efektivitas peralatan menyeluruh: Ketersediaan (Availability), Kinerja (Performance), dan Kualitas Produk (Quality).</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Target World-Class OEE</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">&ge; 85.0%</p>
                    </div>
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Availability (Ketersediaan)</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">92.4%</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Performance (Kinerja)</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">94.1%</p>
                    </div>
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">Quality (Kualitas Bagus)</span>
                        <p class="text-2xl font-black text-purple-900 mt-1">99.2%</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    Kalkulasi OEE dikelompokkan per tanggal periode, shift kerja, dan stasiun kerja untuk mengidentifikasi 6 kerugian besar (Six Big Losses) mesin.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
