@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Portofolio Dana Investasi Unit Link</h1>
            <p class="mt-2 text-sm text-gray-700">Valuasi Nilai Aktiva Bersih (NAV) harian, alokasi unit premi, dan transparansi rasio biaya pengelolaan.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
        @foreach($funds as $fund)
        <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-800">{{ $fund->asset_class }}</span>
                    <span class="text-xs text-gray-500 font-mono">{{ $fund->fund_code }}</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mt-2">{{ $fund->name }}</h3>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-500">Harga Unit (NAV):</span>
                        <span class="font-bold text-gray-900">Rp {{ number_format($fund->current_nav_per_unit_idr, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-500">Total AUM:</span>
                        <span class="font-semibold text-gray-900">Rp {{ number_format($fund->total_aum_idr, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-500">Total Unit:</span>
                        <span class="text-gray-900">{{ number_format($fund->total_units, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Fee Manajer Investasi:</span>
                        <span class="text-gray-900">{{ $fund->management_fee_bps / 100 }}% p.a.</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

