@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Distribusi Surplus Underwriting Dana Tabarru'</h1>
            <p class="text-sm text-gray-600">Audit matematis alokasi surplus berbasis Algoritma Bersertifikat A8 (DSN-MUI Compliance)</p>
        </div>
        <a href="{{ route('insurance.takaful.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Portofolio</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tahun Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dana Tabarru</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gross Surplus</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hak Peserta (Hibah)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cadangan Kontinjensi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mudharabah Pengelola</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sertifikat Algoritma A8</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($distributions as $d)
                <tr>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900">FY{{ $d->fiscal_year }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-700">{{ $d->fund?->name }}</td>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-emerald-600">Rp {{ number_format($d->gross_surplus_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($d->participant_share_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-indigo-600">Rp {{ number_format($d->contingency_reserve_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-amber-600">Rp {{ number_format($d->mudharabah_operator_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-xs font-mono text-gray-500 truncate max-w-xs" title="{{ $d->calculation_certificate }}">
                        {{ substr($d->calculation_certificate, 0, 16) }}...
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada riwayat pembagian surplus underwriting tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $distributions->links() }}
        </div>
    </div>
</div>
@endsection

