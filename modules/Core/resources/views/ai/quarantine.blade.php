@extends('layouts.app')

@section('title', 'Karantina Transaksi & Settlement Hold')
@section('subtitle', 'Daftar Transaksi Terindikasi Anomali Yang Ditahan Sebelum Settlement (D64-2)')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="p-4 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-400 text-sm">
            {{ session('info') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 sm:p-6 border-b border-slate-800 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-white">Transaksi dalam Karantina Aktif</h2>
            <span class="text-xs text-slate-400">Pelepasan mewajibkan persetujuan Risk Manager (Four-Eyes)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="p-3 sm:p-4">Kode / Ref</th>
                        <th class="p-3 sm:p-4">Nasabah</th>
                        <th class="p-3 sm:p-4">Nominal</th>
                        <th class="p-3 sm:p-4">Alasan</th>
                        <th class="p-3 sm:p-4">Aksi Rilis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($quarantines as $q)
                        <tr class="hover:bg-slate-800/50">
                            <td class="p-3 sm:p-4 font-mono font-medium text-white">
                                <div>{{ $q->quarantine_code }}</div>
                                <div class="text-xs text-slate-500">{{ $q->transaction_ref }}</div>
                            </td>
                            <td class="p-3 sm:p-4 font-mono text-xs">{{ $q->customer_ref }}</td>
                            <td class="p-3 sm:p-4 font-mono">Rp {{ number_format($q->amount_idr, 0, ',', '.') }}</td>
                            <td class="p-3 sm:p-4 text-xs text-rose-300">
                                {{ is_array($q->anomaly_reasons) ? implode('; ', $q->anomaly_reasons) : $q->anomaly_reasons }}
                            </td>
                            <td class="p-3 sm:p-4">
                                @if($q->status === 'QUARANTINED')
                                    <form method="POST" action="{{ route('core.ai.quarantine.release', $q) }}" class="flex flex-col sm:flex-row gap-2">
                                        @csrf
                                        <input type="text" name="release_justification" required minlength="5" placeholder="Justifikasi rilis..." class="bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white focus:border-indigo-500">
                                        <button type="submit" class="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-semibold whitespace-nowrap">
                                            Rilis (Four-Eyes)
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-emerald-400 font-semibold">DIRILIS oleh {{ $q->released_by }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500">Tidak ada transaksi terkarantina.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

