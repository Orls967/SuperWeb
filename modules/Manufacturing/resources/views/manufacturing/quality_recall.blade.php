<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Quality Control &amp; Lot Recall Containment</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Penelusuran Silsilah Lot &amp; Protokol Penarikan (Recall)</h3>
                <p class="text-sm text-gray-500 mb-6">Pelacakan maju dan mundur dari pemasok bahan mentah hingga pengiriman pelanggan, karantina otomatis, dan investigasi ketidaksesuaian (NCR).</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-rose-50 border border-rose-100 rounded-lg">
                        <span class="text-xs font-semibold text-rose-600 uppercase">Status Karantina</span>
                        <p class="text-2xl font-black text-rose-900 mt-1">Auto-Blocked</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Investigasi NCR</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">CAPA Mandatory</p>
                    </div>
                    <div class="p-4 bg-teal-50 border border-teal-100 rounded-lg">
                        <span class="text-xs font-semibold text-teal-600 uppercase">Silsilah Genealogi</span>
                        <p class="text-2xl font-black text-teal-900 mt-1">Full Trace (100%)</p>
                    </div>
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Notifikasi Distribusi</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">Real-time Push</p>
                    </div>
                </div>

                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-sm text-rose-800">
                    Ketika status recall diinisiasi, seluruh stok lot terkait pada modul WMS dan Distribution otomatis dibekukan dari alokasi pemenuhan pesanan.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
