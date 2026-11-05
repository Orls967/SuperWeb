@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.micro.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Mikro</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">{{ $loan->loan_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Pembiayaan Mikro Gig Worker</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pekerja (Party)</div>
            <div class="mt-1 text-base font-bold text-slate-900">{{ $loan->worker_party_ref }}</div>
            <div class="text-xs text-slate-500 mt-1">Status: {{ $loan->status }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Skor Amanah G7-K6</div>
            <div class="mt-1 text-2xl font-bold text-emerald-600 font-mono">{{ $loan->amanah_score }}/100</div>
            <div class="text-xs text-slate-500 mt-1">Volatilitas: {{ number_format($loan->income_volatility_bps / 100, 1) }}%</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Plafon Cair</div>
            <div class="mt-1 text-lg font-bold text-slate-900 font-mono">Rp {{ number_format($loan->disbursed_amount_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Limit: Rp {{ number_format($loan->approved_credit_limit_idr / 100, 0, ',', '.') }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sisa Saldo & Potongan</div>
            <div class="mt-1 text-lg font-bold text-emerald-700 font-mono">Rp {{ number_format($loan->remaining_balance_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Deduct: Rp {{ number_format($loan->monthly_deduction_idr / 100, 0, ',', '.') }} / payout</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Riwayat Auto-Deduct Payout Gig / Bounty</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Pemotongan</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Ref Payout</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Total Potong</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Porsi Pokok</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Porsi Margin</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Waktu Potong</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($loan->deductions as $ded)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $ded->deduction_code }}</td>
                            <td class="px-4 py-2 font-mono text-slate-600">{{ $ded->payout_ref }}</td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-slate-900">Rp {{ number_format($ded->deducted_amount_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-mono text-slate-700">Rp {{ number_format($ded->allocated_principal_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($ded->allocated_margin_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-slate-500 text-xs">{{ $ded->deducted_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500">Belum ada pemotongan auto-deduct.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
