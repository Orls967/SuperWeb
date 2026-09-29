@extends('layouts.app')

@section('title', 'Detail Transaksi Ledger')
@section('subtitle', 'Rincian Double-Entry Posting, Jurnal Debit/Kredit & Metadata')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.ledger.index') }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1.5">
            &larr; Kembali ke Daftar Ledger
        </a>
        <span class="text-xs font-mono text-slate-500">ID: #{{ $transaction->id }}</span>
    </div>

    {{-- Transaction Header Card --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-6 backdrop-blur-xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-xs font-semibold uppercase rounded-md bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ $transaction->type }}
                    </span>
                    <span class="text-xs font-mono text-slate-400">
                        {{ $transaction->posted_at->format('d M Y, H:i:s') }}
                    </span>
                </div>
                <h2 class="text-xl font-bold text-white mt-2">{{ $transaction->description }}</h2>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Dibuat Oleh</p>
                <p class="text-sm font-semibold text-slate-200">{{ $transaction->creator ? $transaction->creator->name : 'Sistem Otomatis' }}</p>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-700/60 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
            <div>
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider font-sans">Transaction UUID</span>
                <span class="text-indigo-300 select-all">{{ $transaction->uuid }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider font-sans">Idempotency Key</span>
                <span class="text-slate-300 select-all">{{ $transaction->idempotency_key }}</span>
            </div>
        </div>

        @if($transaction->reference_type && $transaction->reference_id)
            <div class="pt-3 border-t border-slate-700/60 text-xs">
                <span class="text-slate-400">Referensi Objek:</span>
                <span class="font-mono text-slate-200 ml-1">{{ class_basename($transaction->reference_type) }} #{{ $transaction->reference_id }}</span>
            </div>
        @endif
    </div>

    {{-- Double-Entry Journal Table --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60">
            <h3 class="text-base font-bold text-white">Jurnal Pembukuan Berpasangan (Double-Entry)</h3>
            <p class="text-xs text-slate-400 mt-0.5">Semua entri debit dan kredit yang tercatat dalam transaksi ini.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-900/40 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                        <th class="px-6 py-3">Rekening Akun</th>
                        <th class="px-6 py-3">Aset</th>
                        <th class="px-6 py-3 text-right">Debit (-)</th>
                        <th class="px-6 py-3 text-right">Kredit (+)</th>
                        <th class="px-6 py-3 text-right">Saldo Setelah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @php
                        $totalDebits = 0;
                        $totalCredits = 0;
                    @endphp
                    @foreach($transaction->entries as $entry)
                        @php
                            $amt = (float) $entry->amount;
                            if ($amt < 0) {
                                $totalDebits += abs($amt);
                            } else {
                                $totalCredits += $amt;
                            }
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-medium text-white text-sm">{{ $entry->account->name }}</p>
                                <p class="text-xs font-mono text-slate-400 mt-0.5">{{ $entry->account->code }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-bold text-slate-200">
                                {{ $entry->asset_code }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-rose-400 font-medium">
                                {{ $amt < 0 ? $entry->money()->abs()->format() : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-emerald-400 font-medium">
                                {{ $amt > 0 ? $entry->money()->format() : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-slate-400 text-xs">
                                {{ $entry->balanceAfterMoney()->format() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-900/60 font-bold text-xs uppercase tracking-wider border-t border-slate-700">
                        <td colspan="2" class="px-6 py-4 text-slate-400">Total Keseimbangan (Balance Check)</td>
                        <td class="px-6 py-4 text-right font-mono text-rose-400">
                            {{ number_format($totalDebits, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-emerald-400">
                            {{ number_format($totalCredits, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                SEIMBANG ✓
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Metadata Viewer --}}
    @if($transaction->meta)
        <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-6 backdrop-blur-xl">
            <h4 class="text-sm font-bold text-white mb-2">Metadata Tambahan (Audit Trail)</h4>
            <pre class="p-4 rounded-xl bg-slate-900/90 text-slate-300 font-mono text-xs overflow-x-auto border border-slate-700/50">{{ json_encode($transaction->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

</div>
@endsection
