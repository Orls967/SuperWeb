@extends('layouts.app')

@section('title', 'Deteksi Anomali & Anti-Fraud AI')
@section('subtitle', 'Evaluasi Transaksi Real-Time Menggunakan Modified Z-Score & Multi-Aturan (Fase 64.2)')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-white mb-4">Evaluasi Cepat Transaksi</h2>
        <form method="POST" action="{{ route('core.ai.anomalies.screen') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Ref Transaksi</label>
                <input type="text" name="transaction_ref" required placeholder="TX-2026-..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Ref Nasabah / Partner</label>
                <input type="text" name="customer_ref" required placeholder="CUST-..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Nominal (IDR)</label>
                <input type="number" name="amount_idr" required min="1" placeholder="1000000" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Channel</label>
                <select name="channel" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
                    <option value="ONLINE">ONLINE</option>
                    <option value="POS">POS TERMINAL</option>
                    <option value="ATM">ATM / TELLER</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium text-sm transition">
                    Evaluasi Anomali
                </button>
            </div>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 sm:p-6 border-b border-slate-800">
            <h2 class="text-lg font-semibold text-white">Riwayat Evaluasi Anomali</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="p-3 sm:p-4">Ref Transaksi</th>
                        <th class="p-3 sm:p-4">Nominal</th>
                        <th class="p-3 sm:p-4">Skor Risiko</th>
                        <th class="p-3 sm:p-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($quarantines as $item)
                        <tr class="hover:bg-slate-800/50">
                            <td class="p-3 sm:p-4 font-mono font-medium text-white">{{ $item->transaction_ref }}</td>
                            <td class="p-3 sm:p-4 font-mono">Rp {{ number_format($item->amount_idr, 0, ',', '.') }}</td>
                            <td class="p-3 sm:p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold {{ $item->risk_score >= 0.8 ? 'bg-rose-500/20 text-rose-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                                    {{ number_format($item->risk_score, 2) }}
                                </span>
                            </td>
                            <td class="p-3 sm:p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold {{ $item->status === 'QUARANTINED' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-6 text-center text-slate-500">Belum ada catatan transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

