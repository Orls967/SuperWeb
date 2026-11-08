@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <nav class="flex text-xs text-slate-400 gap-2 mb-2">
                    <a href="{{ route('hotel.mice.pipeline') }}" class="hover:text-slate-200">MICE Sales</a>
                    <span>/</span>
                    <span class="text-indigo-400">Wedding & Event Contracts</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-pink-500/20 text-pink-400 rounded-lg border border-pink-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Hub Kontrak Pernikahan Mewah & Termin Pembayaran</h1>
                        <p class="text-sm text-slate-400">Penjadwalan 4 termin pembayaran H17 (DP 20%, T1 30%, T2 30%, Pelunasan 20%) dan hak istimewa Bridal Suite</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-pink-600/30 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Cetak SPK Pernikahan
                </button>
            </div>
        </div>

        <!-- Contract Overview Card -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-850 border border-slate-700 rounded-xl p-6 space-y-6">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 text-xs font-mono font-bold bg-pink-500/20 text-pink-300 border border-pink-500/30 rounded">CTR-WED-2026-071</span>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-purple-500/10 text-purple-300 border border-purple-500/20">TIER: PLATINUM WEDDING</span>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">STATUS: CONFIRMED</span>
                    </div>
                    <h2 class="text-2xl font-bold text-white mt-1">The Royal Wedding of Kevin Sanjaya & Natasha Wilona</h2>
                    <p class="text-sm text-slate-300">Penyelenggara: Citra Wedding Organizer &bull; Tanggal Acara: 15 Juni 2027 &bull; Minimum Tamu: 500 Pax</p>
                </div>

                <div class="text-right">
                    <div class="text-xs text-slate-400">Total Nilai Kontrak</div>
                    <div class="text-2xl font-bold text-emerald-400 font-mono">IDR 285.000.000</div>
                    <p class="text-[11px] text-slate-400">Termasuk Pajak & Layanan Jamuan</p>
                </div>
            </div>

            <!-- Bridal Suite Privilege Banner -->
            <div class="p-4 bg-gradient-to-r from-purple-900/40 via-pink-900/30 to-purple-900/40 border border-purple-500/40 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-purple-500/20 text-purple-300 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </span>
                    <div>
                        <div class="text-sm font-bold text-purple-200">Hak Istimewa: Complimentary Presidential Bridal Suite Terbuka</div>
                        <p class="text-xs text-purple-300/80">Kontrak $\ge \text{IDR } 250.000.000$ memenuhi syarat upgrade gratis 2 malam di Presidential Suite lengkap dengan honeymoon setup.</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-purple-500/30 text-purple-200 rounded-full text-xs font-semibold border border-purple-400/30">
                    SUITE #801 ALLOCATED
                </span>
            </div>

            <!-- Payment Milestones Stepper -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-300">Jadwal Termin Pembayaran Berurutan (Milestone Stepper)</h3>
                    <span class="text-xs text-emerald-400 font-mono">Konservasi Rupiah 100% Terverifikasi</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Milestone 1: DP 20% -->
                    <div class="bg-slate-900/70 border border-emerald-500/50 rounded-xl p-4 space-y-3 relative overflow-hidden">
                        <div class="absolute top-0 right-0 px-2 py-0.5 bg-emerald-500 text-slate-950 font-bold text-[10px] rounded-bl">LUNAS</div>
                        <div class="text-xs font-semibold text-slate-400">Termin 1 (DP 20%)</div>
                        <div class="text-lg font-bold text-white font-mono">IDR 57.000.000</div>
                        <div class="text-xs text-slate-400">
                            <div>Jatuh Tempo: 15 Nov 2026</div>
                            <div class="text-emerald-400 font-medium mt-0.5">&check; Dibayar 10 Nov 2026</div>
                        </div>
                    </div>

                    <!-- Milestone 2: Termin I 30% -->
                    <div class="bg-slate-900/70 border border-emerald-500/50 rounded-xl p-4 space-y-3 relative overflow-hidden">
                        <div class="absolute top-0 right-0 px-2 py-0.5 bg-emerald-500 text-slate-950 font-bold text-[10px] rounded-bl">LUNAS</div>
                        <div class="text-xs font-semibold text-slate-400">Termin II (Mid-term 30%)</div>
                        <div class="text-lg font-bold text-white font-mono">IDR 85.500.000</div>
                        <div class="text-xs text-slate-400">
                            <div>Jatuh Tempo: 17 Mar 2027</div>
                            <div class="text-emerald-400 font-medium mt-0.5">&check; Dibayar 14 Mar 2027</div>
                        </div>
                    </div>

                    <!-- Milestone 3: Termin II 30% -->
                    <div class="bg-slate-900/70 border border-amber-500/50 rounded-xl p-4 space-y-3 relative overflow-hidden">
                        <div class="absolute top-0 right-0 px-2 py-0.5 bg-amber-500 text-slate-950 font-bold text-[10px] rounded-bl">PENDING</div>
                        <div class="text-xs font-semibold text-slate-400">Termin III (Menu Tasting 30%)</div>
                        <div class="text-lg font-bold text-white font-mono">IDR 85.500.000</div>
                        <div class="text-xs text-slate-400">
                            <div>Jatuh Tempo: 16 Mei 2027</div>
                            <div class="text-amber-400 font-medium mt-0.5">Menunggu Pembayaran</div>
                        </div>
                        <button class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded transition">
                            Input Pembayaran
                        </button>
                    </div>

                    <!-- Milestone 4: Pelunasan 20% -->
                    <div class="bg-slate-900/70 border border-slate-700 rounded-xl p-4 space-y-3 relative overflow-hidden opacity-60">
                        <div class="absolute top-0 right-0 px-2 py-0.5 bg-slate-750 text-slate-400 font-bold text-[10px] rounded-bl">TERKUNCI</div>
                        <div class="text-xs font-semibold text-slate-400">Termin IV (Pelunasan 20%)</div>
                        <div class="text-lg font-bold text-white font-mono">IDR 57.000.000</div>
                        <div class="text-xs text-slate-400">
                            <div>Jatuh Tempo: 08 Jun 2027</div>
                            <div class="text-slate-400 mt-0.5">Terbuka setelah T3 Lunas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

