@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Laporan Rekapitulasi Harian Rawat Jalan</h2>
                    <p class="mt-1 text-sm text-gray-400">Rekap pasien & pendapatan per poliklinik vs saldo ledger tanggal {{ $date }}.</p>
                </div>
                <form action="{{ route('hospital.outpatient.reports.daily') }}" method="GET" class="flex items-center gap-2">
                    <input type="date" name="date" value="{{ $date }}" class="px-3 py-1.5 text-sm rounded bg-gray-900 border-gray-700 text-white">
                    <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded">
                        Filter
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-900/60 rounded-lg border border-gray-700">
                    <div class="text-xs text-gray-400">Total Pasien</div>
                    <div class="text-2xl font-bold text-white mt-1">{{ $report['total_patients'] }}</div>
                </div>
                <div class="p-4 bg-gray-900/60 rounded-lg border border-gray-700">
                    <div class="text-xs text-gray-400">Pendapatan Tunai</div>
                    <div class="text-2xl font-bold text-emerald-400 mt-1">Rp {{ number_format($report['cash_income_idr'], 0, ',', '.') }}</div>
                </div>
                <div class="p-4 bg-gray-900/60 rounded-lg border border-gray-700">
                    <div class="text-xs text-gray-400">Pendapatan BPJS / JKN</div>
                    <div class="text-2xl font-bold text-indigo-400 mt-1">Rp {{ number_format($report['bpjs_claim_idr'], 0, ',', '.') }}</div>
                </div>
                <div class="p-4 bg-gray-900/60 rounded-lg border border-gray-700">
                    <div class="text-xs text-gray-400">Status Rekonsiliasi Dua-Sumber</div>
                    <div class="mt-1 flex items-center gap-2">
                        @if($report['is_ledger_reconciled'])
                            <span class="px-2.5 py-1 text-xs rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-600 font-semibold">
                                ✓ Balance (Sesuai Ledger)
                            </span>
                        @else
                            <span class="px-2.5 py-1 text-xs rounded-full bg-rose-900/60 text-rose-300 border border-rose-600 font-semibold">
                                ⚠ Selisih dengan Ledger
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700/50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Poliklinik</th>
                            <th class="px-6 py-3 text-center">Jumlah Pasien</th>
                            <th class="px-6 py-3 text-right">Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($report['by_polyclinic'] as $row)
                            <tr class="hover:bg-gray-750">
                                <td class="px-6 py-4 font-medium text-white">{{ $row['polyclinic'] }}</td>
                                <td class="px-6 py-4 text-center">{{ $row['patient_count'] }}</td>
                                <td class="px-6 py-4 text-right font-bold text-white">Rp {{ number_format($row['revenue_idr'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    Tidak ada data kunjungan pada tanggal ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-750 font-semibold text-white">
                        <tr>
                            <td class="px-6 py-4">Total</td>
                            <td class="px-6 py-4 text-center">{{ $report['total_patients'] }}</td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-400">Rp {{ number_format($report['total_revenue_idr'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
