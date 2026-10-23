@extends('layouts.app')

@section('title', 'RWA Investor Holdings & Whitelist Registry')
@section('subtitle', 'Daftar Kepemilikan Token, Status Verifikasi KYC & Saldo Terkunci (Lockup Escrow)')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">Buku Kepemilikan Investor RWA</h1>
            <p class="text-sm text-slate-400">Verifikasi Kepatuhan OJK / Bappebti & Batasan Konsentrasi Portofolio</p>
        </div>
        <a href="{{ route('rwa.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-200 bg-slate-800/80 hover:bg-slate-700 rounded-xl border border-slate-700">
            &larr; Dashboard RWA
        </a>
    </div>

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Investor</th>
                        <th class="p-3">Aset Riil</th>
                        <th class="p-3">Status KYC</th>
                        <th class="p-3">Saldo Bebas</th>
                        <th class="p-3">Saldo Terkunci (Escrow)</th>
                        <th class="p-3">Jatuh Tempo Lockup</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($holdings as $h)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-semibold text-slate-200">{{ optional($h->user)->name ?? "User #{$h->user_id}" }}</td>
                            <td class="p-3 font-mono font-bold text-violet-400">{{ optional($h->asset)->token_symbol ?? '-' }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded {{ $h->is_kyc_verified ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' }}">
                                    {{ $h->is_kyc_verified ? 'VERIFIED' : 'PENDING' }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-emerald-400 font-bold">{{ number_format($h->token_balance) }}</td>
                            <td class="p-3 font-mono text-amber-400">{{ number_format($h->locked_balance) }}</td>
                            <td class="p-3 text-xs text-slate-400">{{ optional($h->lock_expires_at)->format('d M Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">Belum ada investor terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $holdings->links() }}
        </div>
    </div>
</div>
@endsection
