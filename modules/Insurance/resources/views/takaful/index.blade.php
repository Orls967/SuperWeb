@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Takaful Window & Dana Kebajikan (Tabarru')</h1>
            <p class="text-sm text-gray-600">Pemisahan mutlak rekening dana peserta syariah & tata kelola akad Wakalah bil Ujrah</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('insurance.takaful.surplus') }}" class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Distribusi Surplus Underwriting</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        @foreach($funds as $fund)
        <div class="rounded-lg bg-white p-6 shadow border-t-4 {{ $fund->fund_type === 'TABARRU' ? 'border-emerald-500' : 'border-indigo-500' }}">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ $fund->fund_type }}</span>
            <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $fund->name }}</h2>
            <p class="text-xs font-mono text-gray-500">{{ $fund->fund_code }}</p>
            <div class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Saldo Rekening:</span>
                    <span class="font-bold text-gray-900">Rp {{ number_format($fund->balance_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Iuran Tabarru':</span>
                    <span class="font-medium text-gray-700">Rp {{ number_format($fund->total_contributions_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Klaim Santunan:</span>
                    <span class="font-medium text-rose-600">Rp {{ number_format($fund->total_claims_paid_idr, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Daftar Peserta Asuransi Takaful</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Polis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Iuran Kontribusi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Porsi Tabarru'</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Santunan (Covered)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($participants as $p)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $p->policy_number }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $p->plan_type }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($p->contribution_annual_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-emerald-600">{{ $p->tabarru_portion_bps / 100 }}%</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($p->sum_covered_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">{{ $p->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada peserta Takaful terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $participants->links() }}
        </div>
    </div>
</div>
@endsection

