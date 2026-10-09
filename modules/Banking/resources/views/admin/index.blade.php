@extends('layouts.app')

@section('title', 'Ledger Pembukuan (Admin)')
@section('subtitle', 'Sistem Buku Besar Double-Entry, Akun Sistem & Audit Transaksi')

@section('content')
<div class="space-y-6" x-data="{ adjustModal: false, selectedAccount: null, selectedCode: '', selectedAsset: '' }">

    {{-- Status Flash --}}
    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    {{-- Overview Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-5 backdrop-blur-xl">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Akun Sistem & Kliring</p>
            <h3 class="text-2xl font-bold text-white mt-1">{{ $systemAccounts->count() }} Akun</h3>
            <p class="text-[11px] text-slate-500 mt-1">Clearing, Revenue, Escrow, Exchange, Loan</p>
        </div>

        <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-5 backdrop-blur-xl">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Total Dompet Pengguna</p>
            <h3 class="text-2xl font-bold text-indigo-400 mt-1">{{ $userWalletsCount }} Dompet</h3>
            <p class="text-[11px] text-slate-500 mt-1">Terdaftar di sistem multi-aset</p>
        </div>

        <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-5 backdrop-blur-xl">
            <p class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Integritas Double-Entry</p>
            <div class="flex items-center gap-2 mt-1">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span class="text-lg font-bold text-emerald-400">Terekonsiliasi (100%)</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Setiap debit diimbangi kredit presisi</p>
        </div>
    </div>

    {{-- System Accounts Table --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white">Daftar Akun Sistem & Pool Likuiditas</h3>
                <p class="text-xs text-slate-400 mt-0.5">Struktur rekening pembukuan internal platform.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-900/40 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                        <th class="px-6 py-3">Kode Akun</th>
                        <th class="px-6 py-3">Nama Akun</th>
                        <th class="px-6 py-3">Aset</th>
                        <th class="px-6 py-3">Tipe (Kind)</th>
                        <th class="px-6 py-3 text-right">Saldo Tercatat</th>
                        <th class="px-6 py-3 text-center">Status</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @foreach($systemAccounts as $acc)
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-indigo-300">
                                {{ $acc->code }}
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-200">
                                {{ $acc->name }}
                                @if($acc->allow_negative)
                                    <span class="ml-1 text-[10px] text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">allow_neg</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-bold text-white">
                                {{ $acc->asset_code }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[10px] font-semibold uppercase rounded-md bg-slate-700 text-slate-300">
                                    {{ $acc->kind }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold text-white">
                                {{ $acc->money()->format() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($acc->is_frozen)
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        Frozen
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <button
                                    type="button"
                                    @click="adjustModal = true; selectedAccount = {{ $acc->id }}; selectedCode = '{{ $acc->code }}'; selectedAsset = '{{ $acc->asset_code }}';"
                                    class="px-2.5 py-1 text-xs rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 border border-indigo-500/40 transition-colors">
                                    Adjust
                                </button>

                                <form method="POST" action="{{ route('admin.ledger.freeze', $acc) }}" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        onclick="return confirm('Apakah Anda yakin ingin {{ $acc->is_frozen ? 'mengaktifkan kembali' : 'membekukan' }} akun ini?')"
                                        class="px-2.5 py-1 text-xs rounded-lg {{ $acc->is_frozen ? 'bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-300 border border-emerald-500/40' : 'bg-rose-600/30 hover:bg-rose-600/50 text-rose-300 border border-rose-500/40' }} transition-colors">
                                        {{ $acc->is_frozen ? 'Unfreeze' : 'Freeze' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Transactions Table --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white">Transaksi Ledger Terkini</h3>
                <p class="text-xs text-slate-400 mt-0.5">Jurnal entri pembukuan sistem.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-900/40 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                        <th class="px-6 py-3">Waktu Posting</th>
                        <th class="px-6 py-3">UUID</th>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Deskripsi</th>
                        <th class="px-6 py-3">Entri</th>
                        <th class="px-6 py-3">Dibuat Oleh</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @foreach($transactions as $tx)
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-400">
                                {{ $tx->posted_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-indigo-300">
                                {{ substr($tx->uuid, 0, 13) }}...
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[10px] font-semibold uppercase rounded-md bg-slate-700 text-slate-300">
                                    {{ $tx->type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-200">
                                <p class="font-medium text-sm">{{ $tx->description }}</p>
                                <p class="text-xs text-slate-500 font-mono mt-0.5">ID: #{{ $tx->id }} | Key: {{ substr($tx->idempotency_key, 0, 20) }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400">
                                {{ $tx->entries->count() }} entri
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400">
                                {{ $tx->creator ? $tx->creator->name : 'Sistem' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('admin.ledger.show', $tx) }}"
                                   class="px-3 py-1.5 text-xs rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 border border-indigo-500/40 font-medium transition-colors">
                                    Detail Entri &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-700/60">
            {{ $transactions->links() }}
        </div>
    </div>

    {{-- Manual Adjustment Modal --}}
    <div x-show="adjustModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="adjustModal" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="adjustModal = false"></div>

            <div x-show="adjustModal" x-transition class="inline-block align-bottom bg-slate-800 rounded-2xl border border-slate-700 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                    <div>
                        <h3 class="text-base font-bold text-white">Penyesuaian Manual (Adjustment)</h3>
                        <p class="text-xs text-slate-400 font-mono mt-0.5" x-text="selectedCode"></p>
                    </div>
                    <button @click="adjustModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form :action="'/admin/ledger/accounts/' + selectedAccount + '/adjust'" method="POST" class="mt-4 space-y-4">
                    @csrf
                    {{-- Kunci idempoten tetap sama saat submit ulang setelah validasi gagal --}}
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">
                            Nominal Penyesuaian (<span x-text="selectedAsset"></span>)
                        </label>
                        <input
                            type="number"
                            name="amount"
                            step="any"
                            required
                            placeholder="Gunakan tanda minus (-) untuk mengurangi"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <p class="text-[11px] text-slate-500 mt-1">Contoh: 500000 (menambah) atau -250000 (mengurangi).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Alasan Penyesuaian (Wajib)</label>
                        <textarea
                            name="reason"
                            rows="3"
                            required
                            placeholder="Jelaskan alasan bisnis penyesuaian manual ini..."
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                        <button type="button" @click="adjustModal = false" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-xs font-medium hover:bg-slate-600">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-500 shadow-md shadow-indigo-600/30">
                            Posting Penyesuaian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
