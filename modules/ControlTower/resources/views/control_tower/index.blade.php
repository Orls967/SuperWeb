<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Supply Chain Control Tower & S&OP (ATP, CTP, Peramalan)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Echelon Stock Visibility -->
            <div class="p-6 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Visibilitas Inventori Multi-Eselon</h3>
                <div class="space-y-3 text-sm">
                    @forelse($stocks as $stock)
                        <div class="border rounded p-3 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-800">{{ $stock->item_code }} &ndash; {{ $stock->item_name }}</span>
                                <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ $stock->echelon_node }}</span>
                                <div class="text-xs text-gray-500 mt-1">Klasifikasi: ABC {{ $stock->abc_class }} | XYZ {{ $stock->xyz_class }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-gray-900 font-bold">On Hand: {{ number_format($stock->on_hand_qty) }} | In Transit: {{ number_format($stock->in_transit_qty) }}</div>
                                <div class="text-xs text-gray-500">Reserved: {{ number_format($stock->reserved_qty) }} | Safety: {{ number_format($stock->safety_stock_qty) }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada inventori eselon terpantau.</p>
                    @endforelse
                </div>
            </div>

            <!-- ATP/CTP Promises & Disruption Alerts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Janji Pesanan Konsumen (ATP & CTP)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($promises as $prm)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $prm->promise_code }} ({{ $prm->order_reference }})</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">{{ $prm->promise_status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Permintaan: {{ $prm->requested_qty }} unit | Kirim: {{ $prm->promised_delivery_date->format('Y-m-d') }}</div>
                                <div class="font-mono text-xs mt-1 text-gray-700">ATP Stok: {{ $prm->atp_confirmed_qty }} | CTP Pabrik: {{ $prm->ctp_manufacturing_qty }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada kalkulasi janji pesanan.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Peringatan Gangguan Rantai Pasok</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($alerts as $alt)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $alt->title }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-800">{{ $alt->severity }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $alt->description }}</div>
                                <div class="text-xs font-bold text-red-600 mt-1">Blast Radius: {{ $alt->affected_orders_count }} pesanan terdampak</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Rantai pasok berjalan normal tanpa anomali kritis.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
