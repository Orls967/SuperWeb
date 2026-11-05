@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Asuransi Pertanian Berbasis Indeks Cuaca & NDVI</h1>
            <p class="text-sm text-gray-600">Perlindungan panen petani plasma & otomatisasi offset pinjaman pupuk/benih</p>
        </div>
        <a href="{{ route('insurance.agri.claims') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Daftar Klaim & Offset Pinjaman</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Polis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Komoditas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Luas Lahan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uang Pertanggungan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis Indeks</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ambang Pemicu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($policies as $p)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $p->policy_number }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">{{ $p->commodity }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $p->farm_area_ha }} Ha</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($p->sum_insured_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $p->index_type }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-amber-600 font-bold">{{ $p->trigger_threshold_val }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">{{ $p->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada polis asuransi tani terdaftar.</td>
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

