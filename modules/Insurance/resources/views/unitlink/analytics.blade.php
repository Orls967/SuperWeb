@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analisis Aset Dana Kelolaan Unit Link (AUM)</h1>
            <p class="text-sm text-gray-600">Konsolidasi valuasi Nilai Aktiva Bersih (NAB) harian dan alokasi risiko portofolio</p>
        </div>
        <a href="{{ route('insurance.unitlink.portfolio') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Portofolio</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Asset Under Management (AUM)</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format(($report['total_aum_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Jumlah Sub-Dana Investasi</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $report['total_funds_count'] ?? 0 }} Fund</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Status Kepatuhan OJK</span>
            <p class="text-2xl font-bold text-emerald-600 mt-2">PATUH & TERVERIFIKASI</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Daftar Sub-Dana dan Valuasi Portofolio</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Dana</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Dana</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelas Aset</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NAB per Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valuasi AUM (IDR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($report['funds'] ?? []) as $f)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-indigo-600">{{ $f['fund_code'] }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $f['fund_name'] }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $f['asset_class'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($f['nav_idr'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ number_format($f['total_units'], 2, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono font-semibold text-gray-900">Rp {{ number_format($f['valuation_idr'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

