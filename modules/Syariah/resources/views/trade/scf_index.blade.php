@extends('layouts.app')

@section('title', 'Supply Chain Finance Murabahah')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / SCF</div>
            <h1 class="text-2xl font-bold text-gray-900">Supply Chain Finance (SCF) Murabahah</h1>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. Fasilitas</th>
                        <th class="px-4 py-3">Pembeli (Buyer)</th>
                        <th class="px-4 py-3">Pemasok (Supplier)</th>
                        <th class="px-4 py-3">Pagu Limit</th>
                        <th class="px-4 py-3">Terutilisasi</th>
                        <th class="px-4 py-3">Margin (Bps)</th>
                        <th class="px-4 py-3">Tenor Maks</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($facilities as $fac)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('syariah.trade.scf.show', $fac->id) }}" class="text-amber-600 hover:underline">
                                {{ $fac->facility_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $fac->buyer_party_ref }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $fac->supplier_party_ref }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($fac->limit_amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 font-medium text-amber-600">Rp {{ number_format($fac->utilized_amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ number_format($fac->margin_rate_bps / 100, 2) }}%</td>
                        <td class="px-4 py-3 text-gray-500">{{ $fac->max_tenor_days }} hari</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                {{ $fac->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada fasilitas SCF</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($facilities->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $facilities->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
