<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS 3PL Storage &amp; Handling Billing</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Kalkulator Tagihan 3PL (Penyimpanan &amp; Bongkar-Muat)</h3>
                <p class="text-sm text-gray-500 mb-4">Perhitungan biaya penanganan inbound, outbound, sewa pallet-hari, dan nilai tambah layanan logistik pihak ketiga (3PL).</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Tarif Sewa Pallet/Hari</span>
                        <p class="text-xl font-black text-indigo-900 mt-1">Rp 15.000 / palet</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Handling Inbound</span>
                        <p class="text-xl font-black text-emerald-900 mt-1">Rp 2.500 / unit</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Handling Outbound</span>
                        <p class="text-xl font-black text-amber-900 mt-1">Rp 3.000 / unit</p>
                    </div>
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">Pajak Pertambahan Nilai</span>
                        <p class="text-xl font-black text-purple-900 mt-1">11% PPN Final</p>
                    </div>
                </div>

                @if(session('billing_result'))
                    @php $b = session('billing_result'); @endphp
                    <div class="p-4 mb-6 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                        <h4 class="font-bold text-gray-900">Hasil Kalkulasi Tagihan Klien</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <div>Biaya Storage: <strong>Rp {{ number_format($b['storage_cost_idr']) }}</strong></div>
                            <div>Handling Masuk: <strong>Rp {{ number_format($b['handling_in_cost_idr']) }}</strong></div>
                            <div>Handling Keluar: <strong>Rp {{ number_format($b['handling_out_cost_idr']) }}</strong></div>
                            <div>Total Akhir: <strong class="text-indigo-600">Rp {{ number_format($b['total_billing_idr']) }}</strong></div>
                        </div>
                    </div>
                @endif

                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h4 class="font-semibold text-gray-800 mb-2">Gudang Penyedia Layanan 3PL</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($warehouses ?? [] as $w)
                            <div class="p-3 bg-white border border-gray-200 rounded text-sm">
                                <div class="font-bold text-gray-900">{{ $w->code }} — {{ $w->name }}</div>
                                <div class="text-xs text-gray-500">{{ $w->kind }} &bull; {{ $w->city ?? 'Nasional' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
