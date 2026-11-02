@extends('layouts.app')

@section('title', 'Fleet Lease Amortization & Effective Interest')
@section('subtitle', 'Pengakuan Beban Bunga Efektif & Penyusutan Hak Guna Aset (ROU Amortization)')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Pengakuan Amortisasi Bulanan Aktif</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">No. Kontrak</th>
                        <th class="p-3">Sewa/Bulan</th>
                        <th class="p-3">PV Liabilitas Awal</th>
                        <th class="p-3">Sisa Liabilitas Saat Ini</th>
                        <th class="p-3">Total Teramortisasi</th>
                        <th class="p-3">Eksekusi Pengakuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($contracts as $c)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-blue-400">{{ $c->contract_number }}</td>
                            <td class="p-3 font-mono">Rp {{ number_format($c->monthly_rental_idr) }}</td>
                            <td class="p-3 font-mono text-slate-300">Rp {{ number_format($c->present_value_liability_idr) }}</td>
                            <td class="p-3 font-mono text-amber-400">Rp {{ number_format($c->remaining_liability_idr) }}</td>
                            <td class="p-3 font-mono text-emerald-400">Rp {{ number_format($c->accumulated_amortized_idr) }}</td>
                            <td class="p-3">
                                @if($c->remaining_liability_idr > 0)
                                    <form action="{{ route('fleet.lifecycle.recognize-amortization') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="contract_id" value="{{ $c->id }}">
                                        <button type="submit" class="px-3 py-1 bg-violet-600 hover:bg-violet-500 text-white rounded-lg text-xs font-semibold">
                                            Amortisasi Bulan Ini
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500 font-semibold">Amortisasi Selesai</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">Tidak ada kontrak aktif untuk diamortisasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
