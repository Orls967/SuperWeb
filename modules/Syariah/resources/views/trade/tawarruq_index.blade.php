@extends('layouts.app')

@section('title', 'Commodity Murabahah FX (Tawarruq Munazzam)')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / Tawarruq FX</div>
            <h1 class="text-2xl font-bold text-gray-900">Commodity Murabahah FX (Tawarruq Munazzam)</h1>
        </div>
        <a href="{{ route('syariah.trade.calculator') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-md shadow-sm transition">
            Kalkulator Kurs Tawarruq
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. Order</th>
                        <th class="px-4 py-3">Nasabah</th>
                        <th class="px-4 py-3">Sumber Pokok</th>
                        <th class="px-4 py-3">Target Diterima</th>
                        <th class="px-4 py-3">Kurs Spot</th>
                        <th class="px-4 py-3">Margin (Bps)</th>
                        <th class="px-4 py-3">Total Jual Tangguh</th>
                        <th class="px-4 py-3">Lot Komoditas Bursa</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($orders as $ord)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ord->order_number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ord->customer_party_ref }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ord->source_currency }} {{ number_format($ord->source_amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 font-semibold text-emerald-600">{{ $ord->target_currency }} {{ number_format($ord->target_amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ number_format($ord->spot_rate_bps / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ number_format($ord->markup_rate_bps / 100, 2) }}%</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($ord->total_deferred_price_cents / 100, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-mono text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200">
                                {{ $ord->commodityLot->physical_serial_number ?? 'LOT-BURSA' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                {{ $ord->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-400">Belum ada order tawarruq FX</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
