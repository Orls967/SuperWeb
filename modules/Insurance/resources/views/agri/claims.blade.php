@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Klaim Asuransi Tani & Pemotongan Pinjaman Plasma</h1>
            <p class="text-sm text-gray-600">Integrasi Kontrak G7-K2: Otomatisasi settlement klaim langsung memotong saldo pinjaman di modul Agri</p>
        </div>
        <a href="{{ route('insurance.agri.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Polis Tani</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Klaim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Polis Terkait</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Indeks Aktual</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Santunan (Gross)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Potongan Cicilan Pinjaman</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pencairan Bersih Petani</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($claims as $c)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $c->claim_number }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ $c->policy?->policy_number }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-amber-600">{{ $c->actual_index_val }}</td>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">Rp {{ number_format($c->gross_claim_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-indigo-600 font-semibold">- Rp {{ number_format($c->loan_offset_amount_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-emerald-600 font-bold">Rp {{ number_format($c->net_farmer_payout_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">{{ $c->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada klaim pertanian tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $claims->links() }}
        </div>
    </div>
</div>
@endsection

