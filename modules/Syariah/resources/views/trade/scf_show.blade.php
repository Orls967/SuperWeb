@extends('layouts.app')

@section('title', 'Detail Fasilitas SCF ' . $facility->facility_number)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.scf.index') }}" class="hover:underline">SCF Murabahah</a> / Detail</div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $facility->facility_number }}</h1>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 self-start sm:self-auto">
            Status: {{ $facility->status }}
        </span>
    </div>

    <!-- Facility Overview -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Informasi Fasilitas & Pagu Murabahah</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-gray-500">Nasabah Pembeli:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $facility->buyer_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Pemasok Bahan Baku:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $facility->supplier_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Pagu Total:</span>
                <p class="font-semibold text-gray-900 mt-0.5">Rp {{ number_format($facility->limit_amount_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Pemanfaatan Pagu:</span>
                <p class="font-semibold text-amber-600 mt-0.5">Rp {{ number_format($facility->utilized_amount_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Tingkat Margin Tahunan:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ number_format($facility->margin_rate_bps / 100, 2) }}% ({{ $facility->margin_rate_bps }} bps)</p>
            </div>
            <div>
                <span class="text-gray-500">Maksimum Tenor:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $facility->max_tenor_days }} Hari</p>
            </div>
        </div>
    </div>

    <!-- Invoices List -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Tagihan Invoice & Status Verifikasi Qabdh</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. Invoice SCF</th>
                        <th class="px-4 py-3">Ref Invoice Supplier</th>
                        <th class="px-4 py-3">Biaya Pokok</th>
                        <th class="px-4 py-3">Margin Laba</th>
                        <th class="px-4 py-3">Total Jual Tangguh</th>
                        <th class="px-4 py-3">Verifikasi Qabdh</th>
                        <th class="px-4 py-3">Jatuh Tempo</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($facility->invoices as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $inv->invoice_number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $inv->supplier_invoice_ref }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">Rp {{ number_format($inv->principal_cost_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-emerald-600 font-medium">Rp {{ number_format($inv->margin_profit_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($inv->total_sale_price_cents / 100, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($inv->qabdh_verified_at)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                Sah ({{ $inv->qabdh_verified_at->format('d/m/y') }})
                            </span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                Belum Terverifikasi
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $inv->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                {{ $inv->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada tagihan invoice pada fasilitas ini</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
