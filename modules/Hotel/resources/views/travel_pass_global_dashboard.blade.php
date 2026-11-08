@extends('layouts.app')

@section('title', 'Global Travel Pass Dashboard — Hospitality & Entertainment')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8"
     x-data="{
        searchQuery: '',
        tierFilter: 'ALL',
        showEnrollModal: false,
        newMember: {
            userReference: '',
            initialTier: 'SILVER',
            channel: 'FRONT_DESK'
        }
     }">

    <!-- Top Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-indigo-200 dark:border-indigo-800">
                        Fase 112 · Global Loyalty System
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">17 Lini Ekosistem Terpadu</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Global Travel Pass & Loyalty Command Center
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Pusat pemantauan keanggotaan lintas properti hotel, arena festival, dining, dan aliansi maskapai penerbangan.
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <button type="button" @click="showEnrollModal = true"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md transition text-xs flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Registrasi Pass Baru</span>
                </button>
                <a href="{{ route('hotel.travel_pass.breakage_audit') }}"
                   class="px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition text-xs shadow-sm">
                    Audit Breakage IFRS 15
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- 4 Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span class="font-semibold uppercase tracking-wider">Total Anggota Terdaftar</span>
                    <span class="text-indigo-600 font-bold">Aktif</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalMembers ?? 0) }}</div>
                <div class="text-xs text-slate-400 mt-1">Di 17 Lini Ekosistem</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span class="font-semibold uppercase tracking-wider">Poin Beredar (Circulating PTS)</span>
                    <span class="text-amber-500 font-bold">1 PTS = Rp 100</span>
                </div>
                <div class="text-2xl font-black text-amber-500">{{ number_format($totalPointsCirculating ?? 0) }} PTS</div>
                <div class="text-xs text-slate-400 mt-1">Saldo Aktif Anggota</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span class="font-semibold uppercase tracking-wider">Liabilitas Neraca</span>
                    <span class="text-rose-500 font-bold">Double-Entry</span>
                </div>
                <div class="text-2xl font-black text-rose-600 dark:text-rose-400">Rp {{ number_format($totalLiabilityIdr ?? 0, 0, ',', '.') }}</div>
                <div class="text-xs text-slate-400 mt-1">Akun htl:loyalty_points_liability</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span class="font-semibold uppercase tracking-wider">Distribusi Tingkat Keanggotaan</span>
                    <span class="text-emerald-500 font-bold">4 Tier</span>
                </div>
                <div class="flex items-center space-x-2 text-xs font-bold pt-1">
                    <span class="text-slate-400">S: {{ $tierCounts['SILVER'] ?? 0 }}</span> ·
                    <span class="text-amber-500">G: {{ $tierCounts['GOLD'] ?? 0 }}</span> ·
                    <span class="text-cyan-400">P: {{ $tierCounts['PLATINUM'] ?? 0 }}</span> ·
                    <span class="text-amber-400">B: {{ $tierCounts['BLACK'] ?? 0 }}</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1.5">Algoritma Kualifikasi H13</div>
            </div>
        </div>

        <!-- Featured Radar Gauge Component -->
        <x-hotel::tier_progress_radar_gauge
            tier="PLATINUM"
            :nights="52"
            :spendIdr="142000000"
            :ptsBalance="45000"
            :progressPercentage="69.3" />

        <!-- Member Directory Table -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Direktori Anggota Global Travel Pass</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Daftar pemegang kartu aktif, riwayat menginap, dan saldo poin teragregasi.</p>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <input type="text" x-model="searchQuery" placeholder="Cari nomor pass / nama..."
                               class="w-56 pl-8 pr-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <select x-model="tierFilter" class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200">
                        <option value="ALL">Semua Tier</option>
                        <option value="SILVER">Silver</option>
                        <option value="GOLD">Gold</option>
                        <option value="PLATINUM">Platinum</option>
                        <option value="BLACK">Black</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4">Nomor Pass</th>
                            <th class="py-3 px-4">User Reference</th>
                            <th class="py-3 px-4 text-center">Status Tier</th>
                            <th class="py-3 px-4 text-center">Malam (365H)</th>
                            <th class="py-3 px-4 text-right">Belanja 17 Lini</th>
                            <th class="py-3 px-4 text-right">Saldo Poin PTS</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        @forelse($passes as $pass)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $pass->pass_number }}
                                </td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                                    {{ $pass->user_reference }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider
                                        @if($pass->tier === 'BLACK') bg-zinc-900 text-amber-300 border border-amber-500/50
                                        @elseif($pass->tier === 'PLATINUM') bg-cyan-950 text-cyan-300 border border-cyan-500/50
                                        @elseif($pass->tier === 'GOLD') bg-amber-950 text-amber-300 border border-amber-500/50
                                        @else bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 @endif">
                                        {{ $pass->tier }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center font-semibold">
                                    {{ $pass->cumulative_nights }} Malam
                                </td>
                                <td class="py-3 px-4 text-right font-mono">
                                    Rp {{ number_format($pass->cumulative_spend_idr, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ number_format($pass->pts_balance) }} PTS
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-1.5">
                                        <a href="{{ route('hotel.travel_pass.award_booking') }}" class="p-1.5 text-indigo-600 hover:text-indigo-800" title="Tukar Kamar">
                                            🏨
                                        </a>
                                        <a href="{{ route('hotel.travel_pass.alliance_exchange') }}" class="p-1.5 text-sky-600 hover:text-sky-800" title="Transfer Miles">
                                            ✈️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    Tidak ada data anggota terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($passes, 'links'))
                <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                    {{ $passes->links() }}
                </div>
            @endif
        </div>

        <!-- Ledger Summary Widget -->
        <x-hotel::points_liability_ledger_summary
            :totalLiabilityIdr="$totalLiabilityIdr ?? 250000000"
            :totalBreakageIdr="35000000"
            :totalTransferFeeIdr="8500000"
            :totalPartnerPayableIdr="45000000" />
    </div>

    <!-- Registration Modal -->
    <div x-show="showEnrollModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800"
             @click.away="showEnrollModal = false">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Pendaftaran Anggota Global Travel Pass Baru</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Terbitkan paspor keanggotaan digital terenkripsi untuk akses 17 lini ekosistem.
            </p>
            <div class="space-y-3 text-xs mb-5">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">User Reference / ID Pelanggan</label>
                    <input type="text" x-model="newMember.userReference" placeholder="e.g. USR-VIP-9901"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Tingkat Awal (Initial Tier)</label>
                    <select x-model="newMember.initialTier" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                        <option value="SILVER">Silver Tier (Standar)</option>
                        <option value="GOLD">Gold Tier (Promosi Eksekutif)</option>
                        <option value="PLATINUM">Platinum Tier (Mitra Korporat)</option>
                        <option value="BLACK">Black Tier (VIP Sultan)</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2 text-xs">
                <button type="button" @click="showEnrollModal = false" class="px-4 py-2 border rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                <button type="button" @click="showEnrollModal = false" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700">Terbitkan Pass</button>
            </div>
        </div>
    </div>
</div>
@endsection

