@extends('layouts.app')

@section('title', 'Actuarial Fraud & SIU Risk Evaluation')
@section('subtitle', 'Pendeteksian Klaim Fiktif / Manipulasi Frekuensi Klaim Menggunakan Algoritma Aktuaria')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Antrean Klaim Membutuhkan Evaluasi Resiko</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">No. Klaim</th>
                        <th class="p-3">Santunan</th>
                        <th class="p-3">Status Saat Ini</th>
                        <th class="p-3">Waktu Masuk</th>
                        <th class="p-3">Tindakan Evaluasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($claims as $c)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-amber-400">{{ $c->claim_number }}</td>
                            <td class="p-3 font-mono">Rp {{ number_format($c->payout_amount_idr) }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded {{ $c->status === 'siu_flagged' ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400' }} uppercase">
                                    {{ $c->status }}
                                </span>
                            </td>
                            <td class="p-3 text-xs text-slate-400">{{ optional($c->created_at)->format('d M H:i:s') }}</td>
                            <td class="p-3">
                                <form action="{{ route('insurance.workflow.evaluate-fraud') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="claim_id" value="{{ $c->id }}">
                                    <button type="submit" class="px-3 py-1 bg-violet-600 hover:bg-violet-500 text-white rounded-lg text-xs font-semibold">
                                        Jalankan Model Fraud Aktuaria
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-500">Tidak ada klaim mencurigakan dalam antrean.</td>
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
