@extends('layouts.app')

@section('title', 'Mutasi Rekening')
@section('subtitle', 'Histori Lengkap & Transparan Seluruh Mutasi Saldo')

@section('content')
<div class="space-y-6">

    {{-- Filter & Actions Card --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-6 backdrop-blur-xl">
        <form method="GET" action="{{ route('wallet.mutasi') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-xs font-medium text-slate-300 mb-1">Dari Tanggal</label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="{{ $startDate }}"
                    class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label for="end_date" class="block text-xs font-medium text-slate-300 mb-1">Sampai Tanggal</label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="{{ $endDate }}"
                    class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label for="type" class="block text-xs font-medium text-slate-300 mb-1">Tipe Transaksi</label>
                <select
                    id="type"
                    name="type"
                    class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">Semua Tipe Transaksi</option>
                    <option value="topup" {{ $selectedType === 'topup' ? 'selected' : '' }}>Top Up</option>
                    <option value="transfer" {{ $selectedType === 'transfer' ? 'selected' : '' }}>Transfer</option>
                    <option value="fee" {{ $selectedType === 'fee' ? 'selected' : '' }}>Biaya Admin</option>
                    <option value="payment" {{ $selectedType === 'payment' ? 'selected' : '' }}>Pembayaran</option>
                    <option value="refund" {{ $selectedType === 'refund' ? 'selected' : '' }}>Pengembalian (Refund)</option>
                    <option value="manual_adjustment" {{ $selectedType === 'manual_adjustment' ? 'selected' : '' }}>Penyesuaian Admin</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs transition-all shadow-md shadow-indigo-600/30 flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0118 0z"/></svg>
                    Filter
                </button>

                <a href="{{ route('wallet.mutasi.export', request()->query()) }}"
                   class="py-2 px-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/30 text-emerald-400 font-medium text-xs transition-all flex items-center gap-1.5"
                   title="Unduh format CSV">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Ekspor CSV
                </a>
            </div>
        </form>
    </div>

    {{-- Statement Mutation Table --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white">Daftar Transaksi Rekening</h3>
                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $account->code }}</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400">Saldo Saat Ini:</span>
                <span class="ml-2 font-mono font-bold text-white">{{ $account->money()->format() }}</span>
            </div>
        </div>

        @if($entries->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-700/50 flex items-center justify-center text-slate-400 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-300">Tidak ada data mutasi yang cocok</p>
                <p class="text-xs text-slate-500 mt-1">Coba sesuaikan tanggal filter atau tipe transaksi.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-900/40 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Tanggal & Waktu</th>
                            <th class="px-6 py-3">Tipe</th>
                            <th class="px-6 py-3">Deskripsi Transaksi</th>
                            <th class="px-6 py-3 text-right">Mutasi</th>
                            <th class="px-6 py-3 text-right">Saldo Setelah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-slate-300">
                        @foreach($entries as $entry)
                            @php
                                $isPositive = (float) $entry->amount > 0;
                            @endphp
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500 font-mono">
                                    #{{ $entry->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    {{ $entry->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase rounded-md
                                        @if($entry->transaction->type === 'topup') bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                                        @elseif($entry->transaction->type === 'transfer') bg-blue-500/20 text-blue-300 border border-blue-500/30
                                        @elseif($entry->transaction->type === 'fee') bg-amber-500/20 text-amber-300 border border-amber-500/30
                                        @elseif($entry->transaction->type === 'payment') bg-purple-500/20 text-purple-300 border border-purple-500/30
                                        @else bg-slate-500/20 text-slate-300 border border-slate-500/30 @endif">
                                        {{ $entry->transaction->type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-200">
                                    <p class="font-medium text-sm">{{ $entry->transaction->description }}</p>
                                    <p class="text-xs text-slate-500 font-mono mt-0.5">TX: {{ substr($entry->transaction->uuid, 0, 13) }}...</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold text-sm {{ $isPositive ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $isPositive ? '+' : '' }}{{ $entry->money()->format() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-slate-300 text-xs">
                                    {{ $entry->balanceAfterMoney()->format() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-700/60">
                {{ $entries->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
