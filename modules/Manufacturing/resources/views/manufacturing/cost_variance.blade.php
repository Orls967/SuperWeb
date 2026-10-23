<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manufacturing Standard vs Actual Cost Variance</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Analisis Varians Biaya Manufaktur (Standard Costing)</h3>
                <p class="text-sm text-gray-500 mb-6">Pemisahan varians harga beli, efisiensi bahan (usage), produktivitas tenaga kerja langsung, dan alokasi overhead pabrik.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Standar Material</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">BOM Versioned</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Varians Bahan (Usage)</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">Favourable (-)</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Varians Tenaga Kerja</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">Efficiency Gap</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Kebijakan Ledger</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">Expensed to COGS</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    Semua pencatatan nilai uang disimpan dalam unit minor bilangan bulat (integer IDR) mematuhi Aturan Arsitektur A3 = 0.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
