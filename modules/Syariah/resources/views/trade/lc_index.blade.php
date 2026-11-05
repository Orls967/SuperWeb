@extends('layouts.app')

@section('title', 'Daftar Islamic Letter of Credit')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / Letter of Credit</div>
            <h1 class="text-2xl font-bold text-gray-900">Islamic Letter of Credit (LC)</h1>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3">No. LC</th>
                        <th class="px-4 py-3">Pemohon</th>
                        <th class="px-4 py-3">Penerima (Beneficiary)</th>
                        <th class="px-4 py-3">Nominal LC</th>
                        <th class="px-4 py-3">Margin Setoran</th>
                        <th class="px-4 py-3">Jatuh Tempo</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($lcs as $lc)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('syariah.trade.lc.show', $lc->id) }}" class="text-emerald-600 hover:underline">
                                {{ $lc->lc_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $lc->applicant_party_ref }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $lc->beneficiary_party_ref }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $lc->amount_currency }} {{ number_format($lc->amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-600">Rp {{ number_format($lc->margin_deposit_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $lc->expiry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                {{ $lc->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada Letter of Credit yang diterbitkan</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lcs->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $lcs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
