@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Asuransi Mikro Massal (Micro-Insurance Platform)</h1>
            <p class="text-sm text-gray-600">Perlindungan mikro instan volume tinggi (perjalanan, gadget, kecelakaan harian)</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Polis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis Produk</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Premi Harian</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uang Pertanggungan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Terbit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($policies as $p)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $p->policy_number }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-indigo-600">{{ $p->product_type }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($p->daily_premium_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $p->duration_days }} Hari</td>
                    <td class="px-6 py-4 text-sm font-mono text-emerald-600 font-bold">Rp {{ number_format($p->sum_insured_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-xs text-gray-500">{{ $p->issued_at?->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">{{ $p->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada polis mikro massal tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $policies->links() }}
        </div>
    </div>
</div>
@endsection

