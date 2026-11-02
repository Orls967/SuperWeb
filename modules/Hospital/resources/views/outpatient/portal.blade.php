@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Portal Pasien - Riwayat Kunjungan & Tagihan</h2>
            <p class="mt-1 text-sm text-gray-400">Riwayat kunjungan rawat jalan dan rincian tagihan Anda.</p>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700/50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Kode Kunjungan</th>
                            <th class="px-6 py-3">Poliklinik</th>
                            <th class="px-6 py-3">Dokter</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Kode Tagihan</th>
                            <th class="px-6 py-3">Total Tagihan</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($encounters as $enc)
                            <tr class="hover:bg-gray-750">
                                <td class="px-6 py-4 font-mono font-bold text-white">{{ $enc->encounter_code }}</td>
                                <td class="px-6 py-4">{{ $enc->polyclinic?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $enc->doctor?->user?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 text-xs rounded-full bg-blue-900/60 text-blue-300 border border-blue-600">
                                        {{ $enc->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-emerald-400">
                                    {{ $enc->invoice?->invoice_code ?? '-' }}
                                </td>
                                <td class="px-6 py-4 font-bold text-white">
                                    @if($enc->invoice)
                                        Rp {{ number_format($enc->invoice->total_amount_idr, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <a href="{{ route('hospital.outpatient.encounters.show', $enc) }}" class="text-indigo-400 hover:text-indigo-300 text-xs font-semibold">
                                        Lihat Kunjungan
                                    </a>
                                    @if($enc->invoice)
                                        <a href="{{ route('hospital.outpatient.invoices.show', $enc->invoice) }}" class="text-emerald-400 hover:text-emerald-300 text-xs font-semibold">
                                            Lihat Tagihan
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    Anda belum memiliki riwayat kunjungan rawat jalan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
