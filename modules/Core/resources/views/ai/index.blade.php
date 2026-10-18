@extends('layouts.app')

@section('title', 'AI Governance & Analitik Prediktif')
@section('subtitle', 'Pusat Kendali Dynamic Pricing, Deteksi Anomali Anti-Fraud & Perencanaan Stok Preskriptif (Fase 64)')

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'pricing', showReleaseModal: false, selectedQuarantine: null }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    @if(session('warning'))
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('warning') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-amber-400 hover:text-amber-300">&times;</button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Total Keputusan AI</p>
                <p class="text-2xl font-bold text-slate-100">{{ number_format($kpi['total_decisions']) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">SHA-256 Snapshot Terverifikasi</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Karantina Aktif</p>
                <p class="text-2xl font-bold {{ $kpi['active_quarantines'] > 0 ? 'text-rose-400' : 'text-slate-100' }}">{{ $kpi['active_quarantines'] }}</p>
                <p class="text-xs text-slate-400 mt-0.5">Four-Eyes Review Diwajibkan</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Aturan Dynamic Pricing</p>
                <p class="text-2xl font-bold text-slate-100">{{ $kpi['pricing_rules'] }}</p>
                <p class="text-xs text-cyan-400 mt-0.5">Floor & Ceiling HET Aktif</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Usulan ROP & PO Stok</p>
                <p class="text-2xl font-bold text-slate-100">{{ $kpi['stock_recommendations'] }}</p>
                <p class="text-xs text-amber-400 mt-0.5">Plafon Otonom Level 2</p>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="flex items-center gap-2 border-b border-slate-700/60 pb-3">
        <button type="button" @click="activeTab = 'pricing'" :class="activeTab === 'pricing' ? 'bg-indigo-600 text-white shadow-indigo-500/20' : 'bg-slate-800/60 text-slate-400 hover:text-slate-200'" class="px-5 py-2.5 rounded-xl font-medium text-sm transition shadow-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <span>Dynamic Pricing & Guardrail</span>
        </button>

        <button type="button" @click="activeTab = 'anomaly'" :class="activeTab === 'anomaly' ? 'bg-rose-600 text-white shadow-rose-500/20' : 'bg-slate-800/60 text-slate-400 hover:text-slate-200'" class="px-5 py-2.5 rounded-xl font-medium text-sm transition shadow-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Fraud & Karantina Transaksi</span>
        </button>

        <button type="button" @click="activeTab = 'stock'" :class="activeTab === 'stock' ? 'bg-amber-600 text-white shadow-amber-500/20' : 'bg-slate-800/60 text-slate-400 hover:text-slate-200'" class="px-5 py-2.5 rounded-xl font-medium text-sm transition shadow-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Preskriptif ROP & Stok</span>
        </button>

        <button type="button" @click="activeTab = 'audit'" :class="activeTab === 'audit' ? 'bg-emerald-600 text-white shadow-emerald-500/20' : 'bg-slate-800/60 text-slate-400 hover:text-slate-200'" class="px-5 py-2.5 rounded-xl font-medium text-sm transition shadow-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Audit Trail Snapshot Keputusan</span>
        </button>
    </div>

    {{-- TAB 1: DYNAMIC PRICING --}}
    <div x-show="activeTab === 'pricing'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Simulator --}}
            <div class="lg:col-span-1 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                    <span>Simulator Dynamic Pricing</span>
                </h3>
                <form action="{{ route('admin.ai.calculate-pricing') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Kode Item / Menu</label>
                        <input type="text" name="item_code" value="MENU-NASI-GORENG" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Harga Dasar (IDR)</label>
                        <input type="number" name="base_price_idr" value="35000" step="500" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Stok Sedia</label>
                            <input type="number" name="current_stock" value="45" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Sisa Expired (Jam)</label>
                            <input type="number" name="hours_to_expiry" value="18" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Tingkat Okupansi Ruang / Mall (0.0 - 1.0)</label>
                        <input type="number" name="space_occupancy_rate" value="0.82" step="0.01" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Laju Penjualan</label>
                            <input type="number" name="recent_sales_velocity" value="28" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Baseline Laju</label>
                            <input type="number" name="baseline_sales_velocity" value="15" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-500/20 transition">
                        Kalkulasi & Rekam Snapshot
                    </button>
                </form>
            </div>

            {{-- Rules Table --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                    <span>Aturan Pembatas Guardrail (Floor & HET)</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/50 uppercase tracking-wider text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th class="p-3">Kode & Nama Item</th>
                                <th class="p-3">Harga Dasar</th>
                                <th class="p-3">Floor Price</th>
                                <th class="p-3">Ceiling HET</th>
                                <th class="p-3">Max Surge</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($pricingRules as $rule)
                                <tr class="hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-100">{{ $rule->item_code }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $rule->item_name }}</div>
                                    </td>
                                    <td class="p-3 font-mono">Rp {{ number_format($rule->base_price_idr) }}</td>
                                    <td class="p-3 font-mono text-amber-400">Rp {{ number_format($rule->floor_price_idr) }}</td>
                                    <td class="p-3 font-mono text-emerald-400">Rp {{ number_format($rule->ceiling_price_idr) }}</td>
                                    <td class="p-3">{{ $rule->max_surge_multiplier }}x</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-400">AKTIF</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-slate-400">Belum ada aturan custom; menggunakan default guardrail (0.7x floor - 1.5x HET).</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: ANOMALY & QUARANTINE --}}
    <div x-show="activeTab === 'anomaly'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Test Screen Form --}}
            <div class="lg:col-span-1 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                    <span>Uji Skrining Transaksi Real-Time</span>
                </h3>
                <form action="{{ route('admin.ai.screen-anomaly') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ref Transaksi</label>
                        <input type="text" name="transaction_ref" value="TX-SIM-{{ rand(100,999) }}" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-rose-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ref Customer / Akun</label>
                        <input type="text" name="customer_ref" value="CUST-089-988" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-rose-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nominal Transaksi (IDR)</label>
                        <input type="number" name="amount_idr" value="75000000" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-rose-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Rata-rata Historis</label>
                            <input type="number" name="avg_historical_amount_idr" value="1500000" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-rose-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tx Count (10 mnt)</label>
                            <input type="number" name="recent_tx_count_10m" value="6" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-rose-500">
                        </div>
                    </div>
                    <div class="space-y-2 pt-1">
                        <label class="flex items-center gap-2 text-xs text-slate-300">
                            <input type="checkbox" name="is_late_night" value="1" checked class="rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-0">
                            <span>Transaksi Jam Malam (23:00 - 04:00)</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-300">
                            <input type="checkbox" name="is_split_bill_pattern" value="1" checked class="rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-0">
                            <span>Terindikasi Pola Split-Bill</span>
                        </label>
                    </div>
                    <button type="submit" class="w-full mt-2 py-2.5 bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-rose-500/20 transition">
                        Evaluasi Risiko & Skrining
                    </button>
                </form>
            </div>

            {{-- Quarantines Table --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                    <span>Daftar Transaksi Tertahan di Karantina Settlement</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/50 uppercase tracking-wider text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th class="p-3">Ref Transaksi & Akun</th>
                                <th class="p-3">Nominal</th>
                                <th class="p-3">Skor Risiko</th>
                                <th class="p-3">Alasan Anomali</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($quarantines as $quarantine)
                                <tr class="hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-100">{{ $quarantine->transaction_ref }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $quarantine->customer_ref }}</div>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-slate-100">Rp {{ number_format($quarantine->amount_idr) }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $quarantine->risk_score >= 0.8 ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400' }}">
                                            {{ number_format($quarantine->risk_score * 100, 1) }}%
                                        </span>
                                    </td>
                                    <td class="p-3 max-w-xs truncate text-[11px] text-slate-400">
                                        {{ is_array($quarantine->anomaly_reasons) ? implode('; ', $quarantine->anomaly_reasons) : $quarantine->anomaly_reasons }}
                                    </td>
                                    <td class="p-3">
                                        @if($quarantine->status === 'QUARANTINED')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/20 text-rose-400">TERTAHAN</span>
                                        @elseif($quarantine->status === 'RELEASED')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-400">DIRILIS</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-500/20 text-slate-400">{{ $quarantine->status }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        @if($quarantine->status === 'QUARANTINED')
                                            <form action="{{ route('admin.ai.release-quarantine', $quarantine) }}" method="POST" class="inline-flex items-center gap-1">
                                                @csrf
                                                <input type="hidden" name="release_justification" value="Disetujui supervisor setelah verifikasi biometrik manual">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-[11px] transition">
                                                    Rilis (Four-Eyes)
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[11px] text-slate-500">Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-slate-400">Zero anomali; tidak ada transaksi yang sedang tertahan di karantina.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: STOCK PRESCRIPTION --}}
    <div x-show="activeTab === 'stock'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Prescription Form --}}
            <div class="lg:col-span-1 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span>Generator Rekomendasi Stok ROP</span>
                </h3>
                <form action="{{ route('admin.ai.generate-stock-recommendation') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Kode Barang</label>
                        <input type="text" name="item_code" value="PART-FILTER-OLI-01" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nama Barang</label>
                        <input type="text" name="item_name" value="Filter Oli Mesin Genset Heavy" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Stok Saat Ini</label>
                            <input type="number" name="current_stock" value="12" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Permintaan / Hari</label>
                            <input type="number" name="avg_daily_demand" value="8" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Lead Time (Hari)</label>
                            <input type="number" name="lead_time_days" value="5" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Biaya Satuan (IDR)</label>
                            <input type="number" name="unit_cost_idr" value="125000" required class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Indeks Musiman Eksternal</label>
                        <select name="season_code" class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
                            <option value="REGULAR">Reguler (Faktor 1.00x)</option>
                            <option value="RAMADAN_LEBARAN" selected>Ramadan & Lebaran Rush (Faktor 1.40x)</option>
                            <option value="YEAR_END_HOLIDAY">Libur Akhir Tahun (Faktor 1.30x)</option>
                            <option value="RAINY_SEASON">Musim Hujan Logistik (Faktor 1.20x)</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full mt-2 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-amber-500/20 transition">
                        Hitung ROP & Rilis Draft Usulan PO
                    </button>
                </form>
            </div>

            {{-- Recommendations Table --}}
            <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span>Daftar Usulan Perencanaan Pengadaan Adaptif</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/50 uppercase tracking-wider text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th class="p-3">Kode & Nama Barang</th>
                                <th class="p-3">Stok Sedia / ROP</th>
                                <th class="p-3">Safety Stock</th>
                                <th class="p-3">Usulan Order</th>
                                <th class="p-3">Est. Biaya PO</th>
                                <th class="p-3">Plafon Otonom</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            @forelse($stockRecommendations as $rec)
                                <tr class="hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-100">{{ $rec->item_code }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $rec->item_name }}</div>
                                    </td>
                                    <td class="p-3 font-mono">
                                        <span class="{{ $rec->current_stock <= $rec->calculated_rop ? 'text-rose-400 font-bold' : 'text-slate-200' }}">
                                            {{ $rec->current_stock }}
                                        </span> / {{ $rec->calculated_rop }} unit
                                    </td>
                                    <td class="p-3 font-mono text-cyan-400">{{ $rec->safety_stock }} unit</td>
                                    <td class="p-3 font-mono font-bold text-amber-400">{{ $rec->suggested_order_qty }} unit</td>
                                    <td class="p-3 font-mono">Rp {{ number_format($rec->estimated_cost_idr) }}</td>
                                    <td class="p-3">
                                        @if($rec->requires_human_approval)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/20 text-amber-400">BUTUH APPROVAL (>10JT)</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-400">OTONOM LEVEL 2</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-slate-400">Belum ada usulan stok preskriptif yang diterbitkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 4: AUDIT TRAIL SNAPSHOTS --}}
    <div x-show="activeTab === 'audit'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <span>Riwayat Snapshot Kriptografis Keputusan AI (core_ai_decision_snapshots)</span>
                </h3>
                <span class="text-xs text-slate-400">Perintah Audit: <code class="text-emerald-400 bg-slate-900/60 px-2 py-1 rounded">php artisan ai:audit</code></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/50 uppercase tracking-wider text-slate-400 border-b border-slate-700/50">
                        <tr>
                            <th class="p-3">Kode Keputusan</th>
                            <th class="p-3">Model & Versi</th>
                            <th class="p-3">Seed</th>
                            <th class="p-3">SHA-256 Decision Hash</th>
                            <th class="p-3">Waktu Eksekusi</th>
                            <th class="p-3">Status Kriptografis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($decisionSnapshots as $snap)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono font-semibold text-slate-100">{{ $snap->decision_code }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-indigo-500/20 text-indigo-300">{{ $snap->model_name }}</span>
                                    <span class="text-slate-500 text-[10px]">v{{ $snap->model_version }}</span>
                                </td>
                                <td class="p-3 font-mono text-slate-400">{{ $snap->seed }}</td>
                                <td class="p-3 font-mono text-[11px] text-emerald-400 max-w-xs truncate" title="{{ $snap->decision_hash }}">
                                    {{ $snap->decision_hash }}
                                </td>
                                <td class="p-3 text-slate-400">{{ $snap->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-400 flex items-center gap-1 w-max">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>REPRODUCIBLE</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400">Belum ada snapshot keputusan yang tersimpan di sistem.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
