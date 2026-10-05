<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cross-Border Trade Operations (Ekspor - Impor)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Incoterms 2020 & HS Codes -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Incoterms 2020 Reference</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Kode</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Titik Risiko</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Tanggung Biaya</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @forelse($incoterms as $inc)
                                    <tr>
                                        <td class="px-3 py-2 font-bold">{{ $inc->code }}</td>
                                        <td class="px-3 py-2">{{ $inc->risk_transfer_point }}</td>
                                        <td class="px-3 py-2">{{ $inc->cost_responsibility }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-2 text-gray-500">Belum ada Incoterms.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Negara Mitra Dagang</h3>
                    <div class="space-y-2">
                        @forelse($countries as $cnt)
                            <div class="border rounded p-2 flex justify-between items-center text-sm">
                                <div>
                                    <span class="font-bold">{{ $cnt->code }}</span> - {{ $cnt->name }} ({{ $cnt->currency_code }})
                                </div>
                                @if($cnt->has_fta)
                                    <span class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded font-semibold">FTA Active</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada negara mitra.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Pesanan Ekspor & Impor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pesanan Ekspor Aktif</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($exportOrders as $exp)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-blue-600">{{ $exp->order_number }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-100">{{ $exp->status }}</span>
                                </div>
                                <div class="text-gray-600 mt-1">Pembeli: {{ $exp->buyer_name }} | {{ $exp->incoterm_code }}</div>
                                <div class="font-mono mt-1 text-gray-800">
                                    {{ $exp->currency }} {{ number_format($exp->total_foreign_amount) }} (IDR {{ number_format($exp->total_functional_idr) }})
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">Belum ada pesanan ekspor.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pesanan Impor & Bea Cukai (PIB)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($importOrders as $imp)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-purple-600">{{ $imp->order_number }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-100">{{ $imp->status }}</span>
                                </div>
                                <div class="text-gray-600 mt-1">Pemasok: {{ $imp->supplier_name }} | {{ $imp->incoterm_code }}</div>
                                <div class="font-mono mt-1 text-xs text-gray-700">
                                    Landed Cost: IDR {{ number_format($imp->total_landed_cost_idr) }} (BM: {{ number_format($imp->customs_duty_bm_idr) }}, PPN: {{ number_format($imp->import_vat_ppn_idr) }})
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">Belum ada pesanan impor.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
