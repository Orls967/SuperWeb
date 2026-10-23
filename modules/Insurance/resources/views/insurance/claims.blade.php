@extends('layouts.app')

@section('title', 'Insurance Claims Management')
@section('subtitle', 'Klaim Parametrik Otomatis, Investigasi Fraud Aktuaria & Penyelesaian Ganti Rugi')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Daftar Pengajuan & Realisasi Klaim Asuransi</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">No. Klaim</th>
                        <th class="p-3">No. Polis</th>
                        <th class="p-3">Santunan Dituntut</th>
                        <th class="p-3">Event Pemicu</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Eksekusi Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($claims as $clm)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-rose-400 text-xs">{{ $clm->claim_number }}</td>
                            <td class="p-3 font-mono text-xs">{{ optional($clm->policy)->policy_number ?? '-' }}</td>
                            <td class="p-3 font-mono text-xs text-emerald-400 font-bold">Rp {{ number_format($clm->payout_amount_idr) }}</td>
                            <td class="p-3 text-xs text-slate-400">{{ $clm->trigger_event_id }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded {{ $clm->status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : ($clm->status === 'approved' ? 'bg-blue-500/20 text-blue-400' : 'bg-amber-500/20 text-amber-400') }} uppercase">
                                    {{ $clm->status }}
                                </span>
                            </td>
                            <td class="p-3">
                                @if($clm->status === 'pending_review')
                                    <form action="{{ route('insurance.workflow.evaluate-fraud') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="claim_id" value="{{ $clm->id }}">
                                        <button type="submit" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-semibold">
                                            Skor Fraud
                                        </button>
                                    </form>
                                @elseif($clm->status === 'approved')
                                    <form action="{{ route('insurance.workflow.settle-claim') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="claim_id" value="{{ $clm->id }}">
                                        <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold">
                                            Cairkan Klaim
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500">{{ $clm->status === 'paid' ? 'Selesai Dibayar' : 'Dalam Investigasi' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">Belum ada catatan klaim.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $claims->links() }}
        </div>
    </div>
</div>
@endsection
