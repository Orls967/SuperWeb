<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">MRP Net Requirement Planning</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Simulasi Kebutuhan Bersih Material (MRP Engine)</h3>
                <p class="text-sm text-gray-500 mb-6">Penetapan kebutuhan material kotor terhadap stok di tangan dan jadwal penerimaan terbuka untuk menghasilkan planned orders.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Horizon Perencanaan</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">30 - 90 Hari</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Bucket Time</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">Harian (Daily)</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Aturan Lot-Sizing</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">Lot-for-Lot (L4L)</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Saran PO / WO</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">Automated Draft</p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
                    <div class="text-sm font-semibold text-gray-800 mb-1">Status Run Terakhir</div>
                    <p class="text-xs text-gray-500">Kalkulasi netting sukses dieksekusi tanpa circular BOM loops. Seluruh planned purchase orders dialirkan ke antrian RFQ Pengadaan.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
