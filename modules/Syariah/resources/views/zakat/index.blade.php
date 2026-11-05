@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Zakat Engine & Distribusi 8 Asnaf (D162-4)</h1>
            <p class="text-sm text-slate-600 mt-1">Penilaian nisab emas 85g & haul 354 hari, auto-debit muzakki, alokasi adil proporsional 8 asnaf (LargestRemainder), dan sertifikat zakat digital gapless.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('syariah.zakat.export') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">
                Ekspor Distribusi CSV
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Penilaian Zakat Mal Muzakki</h3>
            <span class="text-xs text-slate-500">{{ $assessments->total() }} Muzakki</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Muzakki</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Tahun Haul</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Harta Memenuhi Syarat</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Utang Jatuh Tempo</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Basis Bersih Zakat</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Wajib Zakat</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Nominal 2.5%</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assessments as $a)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $a->customer?->name ?? "User #{$a->customer_user_id}" }}</td>
                    <td class="px-4 py-3 text-center font-mono text-xs">{{ $a->haul_year }} H</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">Rp {{ number_format($a->qualifying_wealth_idr) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-slate-600">Rp {{ number_format($a->due_debts_idr) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs font-semibold">Rp {{ number_format($a->net_zakat_base_idr) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $a->is_obligated ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ $a->is_obligated ? 'WAJIB' : 'TIDAK' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-xs font-bold text-emerald-700">Rp {{ number_format($a->zakat_amount_idr) }}</td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ $a->status }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada penilaian zakat terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Alokasi & Penyaluran 8 Golongan Asnaf</h3>
            <span class="text-xs text-slate-500">{{ count($distributions) }} Catatan</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Tahun Haul</th>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Golongan Asnaf</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Alokasi Dana</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Tersalurkan</th>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Ref Mustahik</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($distributions as $d)
                <tr>
                    <td class="px-4 py-2 text-center font-mono text-xs">{{ $d->haul_year }} H</td>
                    <td class="px-4 py-2 font-medium text-slate-800 uppercase text-xs">{{ $d->asnaf }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs">Rp {{ number_format($d->allocated_amount_idr) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs font-semibold text-emerald-700">Rp {{ number_format($d->distributed_amount_idr) }}</td>
                    <td class="px-4 py-2 text-xs text-slate-600">{{ $d->mustahik_party_ref ?? 'Lembaga Amil Mitra' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada riwayat distribusi asnaf.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

