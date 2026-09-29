@extends('layouts.app')

@section('title', 'Dompet Digital & Saldo')
@section('subtitle', 'Pusat Keuangan, Saldo Dompet & Manajemen Transaksi')

@section('content')
<div class="space-y-6" x-data="{ topupModal: false, pinModal: false, topupAmount: '500000' }">

    {{-- Status Flash --}}
    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Balance Cards Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Main IDR Wallet Card --}}
        <div class="lg:col-span-2 relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-900/80 via-slate-800/90 to-slate-900 border border-indigo-500/30 p-6 shadow-2xl backdrop-blur-xl">
            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col justify-between h-full space-y-6">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 text-xs font-semibold uppercase tracking-wider rounded-md bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                Dompet Utama
                            </span>
                            @if($idrAccount->is_frozen)
                                <span class="px-2.5 py-1 text-xs font-semibold uppercase tracking-wider rounded-md bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                    Dibekukan
                                </span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-semibold uppercase tracking-wider rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    Aktif
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-2 font-mono">{{ $idrAccount->code }}</p>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-400">Total Saldo Tersedia</p>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mt-1">
                        {{ $idrBalance->format() }}
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-700/50">
                    <button
                        @click="topupModal = true"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Top Up Saldo
                    </button>

                    <a href="{{ route('wallet.transfer') }}"
                       class="px-4 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-100 font-medium text-sm transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transfer Dana
                    </a>

                    <a href="{{ route('wallet.mutasi') }}"
                       class="px-4 py-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 font-medium text-sm border border-slate-700/60 transition-all flex items-center gap-2 ml-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Mutasi Lengkap
                    </a>
                </div>
            </div>
        </div>

        {{-- Security & PIN Card --}}
        <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-6 flex flex-col justify-between backdrop-blur-xl">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Keamanan Dompet</span>
                    <span class="p-2 rounded-lg bg-slate-700/50 text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </span>
                </div>

                <h3 class="text-lg font-bold text-white mb-2">PIN Transaksi (6 Digit)</h3>
                @if($isPinLocked)
                    <div class="p-3 rounded-lg bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs">
                        ⚠️ Akun PIN sedang terkunci karena 5x percobaan salah. Silakan coba lagi nanti.
                    </div>
                @elseif($hasPin)
                    <div class="flex items-center gap-2 text-emerald-400 text-sm font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        PIN Terpasang & Aktif
                    </div>
                    <p class="text-xs text-slate-400 mt-2">PIN digunakan untuk mengonfirmasi setiap transaksi transfer, pembayaran, dan penarikan.</p>
                @else
                    <div class="flex items-center gap-2 text-amber-400 text-sm font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        PIN Belum Diatur
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Atur PIN 6 digit Anda agar dapat melakukan transaksi transfer dan pembayaran.</p>
                @endif
            </div>

            <div class="mt-6">
                <button
                    @click="pinModal = true"
                    type="button"
                    class="w-full py-2.5 px-4 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-medium text-sm transition-all">
                    {{ $hasPin ? 'Ganti PIN Transaksi' : 'Atur PIN Sekarang' }}
                </button>
            </div>
        </div>
    </div>

    {{-- 10 Recent Mutations Section --}}
    <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white">Mutasi Terakhir (10 Transaksi)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Catatan riwayat pemasukan dan pengeluaran saldo rekening Anda.</p>
            </div>
            <a href="{{ route('wallet.mutasi') }}" class="text-xs font-medium text-indigo-400 hover:text-indigo-300 transition-colors">
                Lihat Semua &rarr;
            </a>
        </div>

        @if($recentEntries->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-700/50 flex items-center justify-center text-slate-400 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-300">Belum ada riwayat transaksi</p>
                <p class="text-xs text-slate-500 mt-1">Lakukan Top Up saldo pertama Anda untuk mulai bertransaksi.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-900/40 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                            <th class="px-6 py-3">Tanggal & Waktu</th>
                            <th class="px-6 py-3">Tipe</th>
                            <th class="px-6 py-3">Keterangan</th>
                            <th class="px-6 py-3 text-right">Jumlah</th>
                            <th class="px-6 py-3 text-right">Saldo Berjalan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-slate-300">
                        @foreach($recentEntries as $entry)
                            @php
                                $isPositive = (float) $entry->amount > 0;
                            @endphp
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    {{ $entry->created_at->format('d M Y, H:i') }}
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
                                    @if($entry->transaction->reference)
                                        <p class="text-xs text-slate-500 mt-0.5">Ref: #{{ $entry->transaction->reference_id }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold {{ $isPositive ? 'text-emerald-400' : 'text-rose-400' }}">
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
        @endif
    </div>

    {{-- Top Up Modal --}}
    <div x-show="topupModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="topupModal" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="topupModal = false"></div>

            <div x-show="topupModal" x-transition class="inline-block align-bottom bg-slate-800 rounded-2xl border border-slate-700 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                    <h3 class="text-lg font-bold text-white">Top Up Saldo Dompet</h3>
                    <button @click="topupModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form method="POST" action="{{ route('wallet.topup.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-2">Pilih Nominal Cepat</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach([100000 => '100 Rb', 250000 => '250 Rb', 500000 => '500 Rb', 1000000 => '1 Jt', 2500000 => '2.5 Jt', 5000000 => '5 Jt'] as $val => $lbl)
                                <button
                                    type="button"
                                    @click="topupAmount = '{{ $val }}'"
                                    :class="topupAmount == '{{ $val }}' ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300 font-bold' : 'border-slate-700 bg-slate-900/50 text-slate-300'"
                                    class="py-2.5 px-3 rounded-xl border text-xs text-center transition-all hover:border-slate-600">
                                    {{ $lbl }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label for="amount" class="block text-xs font-medium text-slate-300 mb-1">Atau Masukkan Nominal Sendiri (Rp)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm font-bold">Rp</span>
                            <input
                                type="number"
                                id="amount"
                                name="amount"
                                x-model="topupAmount"
                                min="10000"
                                max="50000000"
                                step="10000"
                                required
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Minimal Rp 10.000, maksimal Rp 50.000.000 per transaksi simulasi kliring.</p>
                    </div>

                    <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                        <button type="button" @click="topupModal = false" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-600">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 shadow-lg shadow-indigo-600/30">
                            Konfirmasi Top Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Set / Change PIN Modal --}}
    <div x-show="pinModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="pinModal" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="pinModal = false"></div>

            <div x-show="pinModal" x-transition class="inline-block align-bottom bg-slate-800 rounded-2xl border border-slate-700 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                    <h3 class="text-lg font-bold text-white">{{ $hasPin ? 'Ganti PIN Transaksi' : 'Atur PIN Transaksi Baru' }}</h3>
                    <button @click="pinModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form method="POST" action="{{ route('wallet.pin.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="pin" class="block text-xs font-medium text-slate-300 mb-1">PIN 6-Digit Baru</label>
                        <input
                            type="password"
                            id="pin"
                            name="pin"
                            maxlength="6"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            required
                            placeholder="Contoh: 123456"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white font-mono text-center text-lg tracking-widest focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="pin_confirmation" class="block text-xs font-medium text-slate-300 mb-1">Ulangi PIN (Konfirmasi)</label>
                        <input
                            type="password"
                            id="pin_confirmation"
                            name="pin_confirmation"
                            maxlength="6"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            required
                            placeholder="Contoh: 123456"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white font-mono text-center text-lg tracking-widest focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                        <button type="button" @click="pinModal = false" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-600">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 shadow-lg shadow-indigo-600/30">
                            Simpan PIN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
