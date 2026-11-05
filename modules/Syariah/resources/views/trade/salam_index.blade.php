@extends('layouts.app')

@section('title', 'Buku Posisi Salam & Parallel Salam')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / Salam</div>
            <h1 class="text-2xl font-bold text-gray-900">Salam & Parallel Salam Komoditas</h1>
        </div>
    </div>

    <!-- Active Salam Contracts -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Kontrak Salam Pokok (Long Position)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. Kontrak</th>
                        <th class="px-4 py-3">Komoditas & Grade</th>
                        <th class="px-4 py-3">Petani / Penjual</th>
                        <th class="px-4 py-3">Kuantum Dipesan</th>
                        <th class="px-4 py-3">Kuantum Diterima</th>
                        <th class="px-4 py-3">Advance Payment</th>
                        <th class="px-4 py-3">Jadwal Serah</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($salams as $salam)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('syariah.trade.salam.show', $salam->id) }}" class="text-blue-600 hover:underline">
                                {{ $salam->contract_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $salam->commodity_code }} ({{ $salam->commodity_grade }})</td>
                        <td class="px-4 py-3 text-gray-600">{{ $salam->seller_party_ref }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($salam->quantity_ordered) }} {{ $salam->quantity_unit }}</td>
                        <td class="px-4 py-3 text-emerald-600 font-medium">{{ number_format($salam->quantity_delivered) }} {{ $salam->quantity_unit }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($salam->advance_payment_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $salam->promised_delivery_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $salam->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada kontrak salam</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($salams->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $salams->links() }}
        </div>
        @endif
    </div>

    <!-- Parallel Salam Hedging -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Parallel Salam Hedging ke Off-Taker (Short Position)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. Hedge</th>
                        <th class="px-4 py-3">Kontrak Induk</th>
                        <th class="px-4 py-3">Off-Taker</th>
                        <th class="px-4 py-3">Kuantum Lindung Nilai</th>
                        <th class="px-4 py-3">Harga Jual Disepakati</th>
                        <th class="px-4 py-3">Target Penyerahan</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($hedges as $h)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $h->hedge_contract_number }}</td>
                        <td class="px-4 py-3 text-blue-600">{{ $h->salamContract->contract_number ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $h->offtaker_party_ref }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($h->quantity_hedged) }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($h->agreed_sale_price_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $h->delivery_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                {{ $h->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada kontrak parallel salam</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
