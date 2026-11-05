@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Pasar Sukuk Syariah & RWA SPV (D162-1)</h1>
            <p class="text-sm text-slate-600 mt-1">Penerbitan Sukuk Ijarah/Mudharabah berbasis aset riil RWA (G7-K5), sertifikasi opini DPS four-eyes, dan distribusi imbal hasil LargestRemainder.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('syariah.sukuk.export') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">
                Ekspor CSV
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode Sukuk</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Nama Fasilitas</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Aset Dasar RWA</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Nilai Nominal</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Kupon BPS</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Jatuh Tempo</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Terdistribusi</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($facilities as $f)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ $f->sukuk_code }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $f->facility_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $f->asset_code }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-900">Rp {{ number_format($f->face_value_idr) }}</td>
                    <td class="px-4 py-3 text-center text-emerald-700 font-medium">{{ number_format($f->annual_coupon_bps / 100, 2) }}%</td>
                    <td class="px-4 py-3 text-center text-xs text-slate-600">{{ $f->maturity_date?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                            {{ $f->status->value === 'ISSUED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                            {{ $f->status->value }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-xs font-mono text-slate-700">Rp {{ number_format($f->total_coupon_distributed_idr) }}</td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('syariah.sukuk.show', $f->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium text-xs">Detail</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada fasilitas sukuk terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($facilities, 'links'))
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $facilities->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
