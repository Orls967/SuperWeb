@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-indigo-500/20 text-indigo-400 rounded-lg border border-indigo-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">MICE & Global Event Sales Pipeline</h1>
                        <p class="text-sm text-slate-400">Manajemen prospek B2B, penawaran multi-komponen, timelock harga, dan penegakan guardrail margin $\ge 40\%$</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('hotel.mice.calculator') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Kalkulator Margin Event
                </a>
                <button type="button" onclick="document.getElementById('modal-create-lead').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-indigo-600/30 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Prospek MICE Baru
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Total Prospek Aktif</span>
                    <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-white">{{ $leadsCount ?? 28 }}</div>
                    <span class="text-xs text-blue-400 font-medium">+14% MoM</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Konferensi, Wedding & Pameran</p>
            </div>

            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Estimasi Nilai Pipeline</span>
                    <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-white">IDR {{ number_format($totalPipelineValue ?? 4850000000, 0, ',', '.') }}</div>
                    <span class="text-xs text-emerald-400 font-medium">Tertimbang 68%</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Total akumulasi penawaran</p>
            </div>

            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Rata-Rata Gross Margin</span>
                    <span class="p-1.5 bg-amber-500/10 text-amber-400 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-white">{{ number_format($avgGrossMargin ?? 47.8, 1) }}%</div>
                    <span class="text-xs text-emerald-400 font-medium">Batas Aman $\ge 40\%$</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Guardrail H16 aktif otomatis</p>
            </div>

            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Kontrak Terkunci (SPK)</span>
                    <span class="p-1.5 bg-purple-500/10 text-purple-400 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-white">{{ $confirmedContractsCount ?? 12 }} Event</div>
                    <span class="text-xs text-purple-400 font-medium">Q4 2026</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Semua termin pembayaran terbit</p>
            </div>
        </div>

        <!-- Pipeline Kanban Board -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
            <!-- Stage 1: New Leads -->
            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                        <h3 class="font-semibold text-sm text-slate-200">Prospek Masuk (Leads)</h3>
                    </div>
                    <span class="px-2 py-0.5 text-xs bg-slate-700 text-slate-300 rounded-full font-semibold">4</span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-800/90 border border-slate-700 rounded-lg p-3 shadow-sm hover:border-slate-600 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded font-medium">CONFERENCE</span>
                            <span class="text-slate-400">H-60</span>
                        </div>
                        <h4 class="font-medium text-slate-100 text-sm mt-2">Indonesia Fintech Summit 2026</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Asosiasi Fintech Indonesia &bull; 450 Pax</p>
                        <div class="mt-3 pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
                            <span class="text-slate-400">Budget: IDR 350 Jt</span>
                            <button class="text-indigo-400 hover:text-indigo-300 font-medium">Buat Proposal &rarr;</button>
                        </div>
                    </div>

                    <div class="bg-slate-800/90 border border-slate-700 rounded-lg p-3 shadow-sm hover:border-slate-600 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="px-2 py-0.5 bg-pink-500/10 text-pink-400 border border-pink-500/20 rounded font-medium">WEDDING</span>
                            <span class="text-slate-400">H-180</span>
                        </div>
                        <h4 class="font-medium text-slate-100 text-sm mt-2">The Royal Wedding of Kevin & Natasha</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Wedding Organizer Citra &bull; 600 Pax</p>
                        <div class="mt-3 pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
                            <span class="text-slate-400">Budget: IDR 450 Jt</span>
                            <button class="text-indigo-400 hover:text-indigo-300 font-medium">Buat Proposal &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stage 2: Proposal & Quotation Timelock -->
            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                        <h3 class="font-semibold text-sm text-slate-200">Proposal & Timelock</h3>
                    </div>
                    <span class="px-2 py-0.5 text-xs bg-slate-700 text-slate-300 rounded-full font-semibold">3</span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-800/90 border border-amber-500/30 rounded-lg p-3 shadow-sm">
                        <div class="flex items-center justify-between text-xs">
                            <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded font-medium">TIMELOCK AKTIF</span>
                            <span class="text-amber-300 font-medium">Sisa 4 Hari</span>
                        </div>
                        <h4 class="font-medium text-slate-100 text-sm mt-2">ASEAN Energy Ministerial Gala</h4>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-1.5 py-0.5 text-[11px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-mono">GM: 48.5%</span>
                            <span class="text-xs text-slate-400">Guardrail Lolos</span>
                        </div>
                        <div class="mt-3 pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
                            <span class="text-white font-semibold">IDR 280.000.000</span>
                            <button class="px-2 py-1 bg-indigo-600/30 text-indigo-300 hover:bg-indigo-600/50 rounded">Konversi SPK</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stage 3: Negotiation & Guardrail Audit -->
            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                        <h3 class="font-semibold text-sm text-slate-200">Negosiasi & Otorisasi</h3>
                    </div>
                    <span class="px-2 py-0.5 text-xs bg-slate-700 text-slate-300 rounded-full font-semibold">2</span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-800/90 border border-slate-700 rounded-lg p-3 shadow-sm">
                        <div class="flex items-center justify-between text-xs">
                            <span class="px-2 py-0.5 bg-purple-500/10 text-purple-400 border border-purple-500/20 rounded font-medium">NEGOSIASI GM</span>
                            <span class="text-slate-400">Revisi V2</span>
                        </div>
                        <h4 class="font-medium text-slate-100 text-sm mt-2">Global Medical Devices Expo</h4>
                        <p class="text-xs text-slate-400 mt-0.5">30 Stan Expo + Ballroom Plenary</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-1.5 py-0.5 text-[11px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-mono">GM: 42.1%</span>
                            <span class="text-xs text-slate-400">Stan + Utilitas KVA</span>
                        </div>
                        <div class="mt-3 pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
                            <span class="text-white font-semibold">IDR 520.000.000</span>
                            <button class="text-emerald-400 hover:text-emerald-300 font-medium">Setujui SPK</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stage 4: Confirmed & Contract Locked -->
            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <h3 class="font-semibold text-sm text-slate-200">Kontrak Terbit (SPK)</h3>
                    </div>
                    <span class="px-2 py-0.5 text-xs bg-slate-700 text-slate-300 rounded-full font-semibold">5</span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-800/90 border border-emerald-500/30 rounded-lg p-3 shadow-sm">
                        <div class="flex items-center justify-between text-xs">
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-medium">SPK LENGKAP</span>
                            <span class="text-slate-400">CTR-8812</span>
                        </div>
                        <h4 class="font-medium text-slate-100 text-sm mt-2">International Oceanology Congress</h4>
                        <p class="text-xs text-slate-400 mt-0.5">200 Kamar Blok &bull; BEO 3 Hari</p>
                        <div class="mt-3 pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
                            <span class="text-white font-semibold">IDR 380.000.000</span>
                            <a href="{{ route('hotel.mice.room-blocks') }}" class="text-indigo-400 hover:text-indigo-300 font-medium">Pantau Allotment &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
