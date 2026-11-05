@extends('layouts.app')

@section('title', 'Detail Salam ' . $salam->contract_number)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.salam.index') }}" class="hover:underline">Salam Komoditas</a> / Detail</div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $salam->contract_number }}</h1>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 self-start sm:self-auto">
            {{ $salam->status }}
        </span>
    </div>

    <!-- Contract Details -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Informasi Kontrak Salam Pokok (Muslam Fiih)</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div>
                <span class="text-gray-500">Komoditas & Standar Mutu:</span>
                <p class="font-bold text-gray-900 text-sm mt-0.5">{{ $salam->commodity_code }} - {{ $salam->commodity_grade }}</p>
            </div>
            <div>
                <span class="text-gray-500">Produsen / Petani Penjual:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $salam->seller_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Pembeli (Shahibul Maal):</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $salam->buyer_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Kuantum Dipesan:</span>
                <p class="font-semibold text-gray-900 mt-0.5">{{ number_format($salam->quantity_ordered) }} {{ $salam->quantity_unit }}</p>
            </div>
            <div>
                <span class="text-gray-500">Kuantum Terealisasi:</span>
                <p class="font-semibold text-emerald-600 mt-0.5">{{ number_format($salam->quantity_delivered) }} {{ $salam->quantity_unit }}</p>
            </div>
            <div>
                <span class="text-gray-500">Advance Payment Tunai 100%:</span>
                <p class="font-semibold text-gray-900 mt-0.5">Rp {{ number_format($salam->advance_payment_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Tanggal Janji Serah:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $salam->promised_delivery_date->format('d M Y') }}</p>
            </div>
            <div>
                <span class="text-gray-500">Gudang Penampungan:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $salam->delivery_warehouse_code }}</p>
            </div>
        </div>
    </div>

    <!-- Deliveries and Hedging -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Physical Deliveries -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Riwayat Penyerahan Fisik & Resi Gudang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Batch Ref</th>
                            <th class="px-4 py-3">Kuantum Diterima</th>
                            <th class="px-4 py-3">No. Resi Gudang</th>
                            <th class="px-4 py-3">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($salam->deliveries as $del)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $del->delivery_batch_ref }}</td>
                            <td class="px-4 py-3 font-semibold text-emerald-600">{{ number_format($del->quantity_received) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $del->warehouse_receipt_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $del->delivered_at->format('d M Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada penyerahan barang</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Parallel Hedges -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Kontrak Parallel Salam Off-Taker</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3">No. Hedge</th>
                            <th class="px-4 py-3">Off-Taker</th>
                            <th class="px-4 py-3">Kuantum</th>
                            <th class="px-4 py-3">Harga Jual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($salam->parallelHedges as $h)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $h->hedge_contract_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $h->offtaker_party_ref }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($h->quantity_hedged) }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($h->agreed_sale_price_cents / 100, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada parallel hedge terdaftar</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
