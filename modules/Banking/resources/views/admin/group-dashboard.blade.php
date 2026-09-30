@extends('layouts.app')

@section('title', 'Dashboard Grup Konsolidasi')
@section('subtitle', 'Laporan Laba Rugi (P&L) Multi-Lini Bisnis Real-Time dari Buku Besar Double-Entry')

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'all' }">

    {{-- Top Header Action & Period Filter --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-800/40 p-4 rounded-2xl border border-slate-700/50 backdrop-blur-xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-white">Konsolidasi Holding 4 Pilar Usaha</h2>
                <p class="text-xs text-slate-400">Otomotif • Perbankan & Kripto • Kuliner • Properti Mall</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-medium">Periode:</span>
            <div class="inline-flex rounded-xl bg-slate-900/80 p-1 border border-slate-700/60">
                <a href="{{ route('admin.group-dashboard', ['days' => 7]) }}" class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ ($summary['days'] ?? 30) == 7 ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">7 Hari</a>
                <a href="{{ route('admin.group-dashboard', ['days' => 30]) }}" class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ ($summary['days'] ?? 30) == 30 ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">30 Hari</a>
                <a href="{{ route('admin.group-dashboard', ['days' => 90]) }}" class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ ($summary['days'] ?? 30) == 90 ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">90 Hari</a>
            </div>
        </div>
    </div>

    {{-- Consolidated KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {{-- Total Revenue --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800/90 to-slate-900/90 border border-slate-700/60 p-5 backdrop-blur-xl shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendapatan</p>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <h3 class="text-2xl font-black text-white mt-2 font-mono">Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</h3>
            <p class="text-[11px] text-emerald-400/80 mt-1 flex items-center gap-1 font-medium">
                <span>&bull;</span> Arus masuk dari seluruh transaksi
            </p>
        </div>

        {{-- Total Expense --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800/90 to-slate-900/90 border border-slate-700/60 p-5 backdrop-blur-xl shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Beban Operasional</p>
                <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" /></svg>
                </div>
            </div>
            <h3 class="text-2xl font-black text-rose-400 mt-2 font-mono">Rp {{ number_format($summary['total_expense'], 0, ',', '.') }}</h3>
            <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 font-medium">
                <span>&bull;</span> HPP, waste, promosi & fasilitas
            </p>
        </div>

        {{-- Net Profit --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800/90 to-slate-900/90 border border-slate-700/60 p-5 backdrop-blur-xl shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Laba Bersih Konsolidasi</p>
                <div class="w-8 h-8 rounded-lg {{ $summary['net_profit'] >= 0 ? 'bg-indigo-500/10 text-indigo-400' : 'bg-rose-500/10 text-rose-400' }} flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                </div>
            </div>
            <h3 class="text-2xl font-black {{ $summary['net_profit'] >= 0 ? 'text-indigo-400' : 'text-rose-400' }} mt-2 font-mono">
                Rp {{ number_format($summary['net_profit'], 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 font-medium">
                <span>&bull;</span> Net Profit = Total Omzet - Beban
            </p>
        </div>

        {{-- Profit Margin --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800/90 to-slate-900/90 border border-slate-700/60 p-5 backdrop-blur-xl shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Margin Laba Bersih</p>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <h3 class="text-2xl font-black text-amber-400 mt-2 font-mono">{{ $summary['margin_percent'] }}%</h3>
            <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 font-medium">
                <span>&bull;</span> Rasio profitabilitas keseluruhan
            </p>
        </div>
    </div>

    {{-- Business Lines P&L Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($lines as $lineKey => $line)
        <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden flex flex-col justify-between backdrop-blur-xl hover:border-slate-600 transition">
            <div class="p-5 border-b border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full" style="background-color: {{ $line['color'] }}"></span>
                    <h4 class="font-bold text-white text-sm">{{ $line['name'] }}</h4>
                </div>
                <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold {{ $line['net_profit'] >= 0 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                    {{ $line['margin_percent'] }}% Margin
                </span>
            </div>

            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Pendapatan (Revenue):</span>
                    <span class="font-mono font-bold text-emerald-400">Rp {{ number_format($line['revenue'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Beban (Expense):</span>
                    <span class="font-mono font-bold text-rose-400">Rp {{ number_format($line['expense'], 0, ',', '.') }}</span>
                </div>
                <div class="pt-3 border-t border-slate-700/50 flex items-center justify-between text-sm">
                    <span class="font-semibold text-slate-300">Laba Bersih:</span>
                    <span class="font-mono font-black {{ $line['net_profit'] >= 0 ? 'text-indigo-400' : 'text-rose-400' }}">
                        Rp {{ number_format($line['net_profit'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <div class="px-5 py-3 bg-slate-900/40 border-t border-slate-700/50 flex items-center justify-between text-[11px] text-slate-400">
                <span>{{ count($line['accounts']) }} Akun Pembukuan</span>
                <button @click="activeTab = '{{ $lineKey }}'" class="text-indigo-400 hover:text-indigo-300 font-medium">Lihat Rincian &rarr;</button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Consolidated 30-Day Trend Chart & Summary Bar --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-6 backdrop-blur-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-700/60 gap-4">
            <div>
                <h3 class="text-base font-bold text-white">Tren Laba Bersih Harian (30 Hari)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Pergerakan akumulasi margin bersih dari entri buku besar otomatis.</p>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-500"></span><span class="text-slate-400">Otomotif</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-purple-500"></span><span class="text-slate-400">Keuangan</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500"></span><span class="text-slate-400">Kuliner</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span><span class="text-slate-400">Mall Properti</span></div>
            </div>
        </div>

        {{-- Visual CSS Bar Chart for 30 Days --}}
        <div class="mt-6 overflow-x-auto">
            <div class="min-w-[700px] h-48 flex items-end gap-1.5 px-2 pb-2">
                @php
                    $maxAbs = 1;
                    foreach($trends as $t) {
                        $maxAbs = max($maxAbs, abs($t['net_profit']), abs($t['total_revenue']));
                    }
                @endphp

                @foreach($trends as $t)
                @php
                    $heightPercent = min(100, max(6, round((abs($t['net_profit']) / $maxAbs) * 100)));
                    $isPositive = $t['net_profit'] >= 0;
                @endphp
                <div class="flex-1 flex flex-col items-center gap-1 group relative">
                    {{-- Tooltip --}}
                    <div class="absolute -top-12 z-20 hidden group-hover:block bg-slate-950 border border-slate-700 rounded-lg px-2.5 py-1 text-[10px] text-white whitespace-nowrap shadow-xl">
                        <span class="font-bold">{{ $t['label'] }}:</span>
                        Rp {{ number_format($t['net_profit'], 0, ',', '.') }}
                    </div>

                    {{-- Bar --}}
                    <div class="w-full rounded-t-sm transition-all duration-300 {{ $isPositive ? 'bg-gradient-to-t from-indigo-600 to-emerald-400 group-hover:brightness-125' : 'bg-rose-500/80' }}"
                         style="height: {{ $heightPercent }}%;">
                    </div>
                    <span class="text-[9px] text-slate-500 font-mono rotate-45 origin-left truncate mt-1">{{ substr($t['date'], 5) }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Detailed Account Ledgers per Business Line --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 overflow-hidden backdrop-blur-xl">
        <div class="p-6 border-b border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-white">Rincian Akun Buku Besar Pendapatan & Beban</h3>
                <p class="text-xs text-slate-400 mt-0.5">Saldo akun-akun ledger double-entry spesifik per lini bisnis.</p>
            </div>

            <div class="flex items-center gap-1 bg-slate-900/60 p-1 rounded-xl border border-slate-700/60 text-xs">
                <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-3 py-1 rounded-lg transition">Semua Lini</button>
                <button @click="activeTab = 'otomotif'" :class="activeTab === 'otomotif' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-3 py-1 rounded-lg transition">Otomotif</button>
                <button @click="activeTab = 'keuangan'" :class="activeTab === 'keuangan' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-3 py-1 rounded-lg transition">Keuangan</button>
                <button @click="activeTab = 'kuliner'" :class="activeTab === 'kuliner' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-3 py-1 rounded-lg transition">Kuliner</button>
                <button @click="activeTab = 'properti'" :class="activeTab === 'properti' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-3 py-1 rounded-lg transition">Properti</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-900/50 text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-700/50">
                        <th class="px-6 py-3.5">Lini Bisnis</th>
                        <th class="px-6 py-3.5">Kode Akun Ledger</th>
                        <th class="px-6 py-3.5">Nama Akun</th>
                        <th class="px-6 py-3.5">Kategori</th>
                        <th class="px-6 py-3.5 text-right">Total Akumulasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 font-mono text-xs">
                    @foreach($lines as $lineKey => $line)
                        @foreach($line['accounts'] as $acc)
                        <tr x-show="activeTab === 'all' || activeTab === '{{ $lineKey }}'" class="hover:bg-slate-700/20 transition">
                            <td class="px-6 py-3 font-sans font-medium text-slate-300">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $line['color'] }}"></span>
                                    {{ $line['name'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-indigo-300">{{ $acc['code'] }}</td>
                            <td class="px-6 py-3 font-sans text-slate-300">{{ $acc['name'] }}</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $acc['type'] === 'revenue' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                                    {{ $acc['type'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right font-bold {{ $acc['type'] === 'revenue' ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $acc['type'] === 'revenue' ? '+' : '-' }} Rp {{ number_format($acc['amount'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
