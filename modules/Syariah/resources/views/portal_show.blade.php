@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Detail Akad Murabahah {{ $contract->contract_code }}</h1>
        <p class="text-sm text-slate-600 mt-1">{{ $contract->asset_description }} — Status: {{ $contract->status }}</p>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Jatuh Tempo</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Pokok</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Margin Tetap</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Total Angsuran</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($contract->schedules as $s)
                <tr>
                    <td class="px-4 py-3 text-center">{{ $s->installment_no }}</td>
                    <td class="px-4 py-3">{{ $s->due_date?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($s->principal_component_idr) }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($s->margin_component_idr) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($s->installment_amount_idr) }}</td>
                    <td class="px-4 py-3">{{ $s->status }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
