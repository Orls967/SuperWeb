@extends('layouts.app')

@section('title', 'Award Night Booking Portal — Dynamic Pricing & 115% Floor')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8"
     x-data="{
        selectedProperty: 'PROP-BALI-01',
        selectedRoom: 'DELUXE_OCEAN',
        occupancyRate: 0.75,
        basePoints: 15000,
        marginalCostIdr: 300000,
        memberTier: 'GOLD',
        get finalPoints() {
            let sigmoid = 1.0 / (1.0 + Math.exp(-10.0 * (this.occupancyRate - 0.70)));
            let surge = 1.0 + (1.5 * sigmoid);
            let discount = this.memberTier === 'BLACK' ? 0.15 : (this.memberTier === 'PLATINUM' ? 0.10 : (this.memberTier === 'GOLD' ? 0.05 : 0.0));
            let demandPts = Math.ceil(this.basePoints * surge * (1 - discount));
            let floorPts = Math.ceil((this.marginalCostIdr * 1.15) / 100);
            return Math.max(demandPts, floorPts);
        },
        get isFloorGuaranteed() {
            let sigmoid = 1.0 / (1.0 + Math.exp(-10.0 * (this.occupancyRate - 0.70)));
            let surge = 1.0 + (1.5 * sigmoid);
            let discount = this.memberTier === 'BLACK' ? 0.15 : (this.memberTier === 'PLATINUM' ? 0.10 : (this.memberTier === 'GOLD' ? 0.05 : 0.0));
            let demandPts = Math.ceil(this.basePoints * surge * (1 - discount));
            let floorPts = Math.ceil((this.marginalCostIdr * 1.15) / 100);
            return floorPts > demandPts;
        }
     }">

    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-emerald-200 dark:border-emerald-800">
                        Algoritma H11 · Dynamic Award Pricing
                    </span>
                    <span class="text-xs text-slate-500">Floor Multiplier 115% Terproteksi</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Portal Pemesanan Kamar Hadiah (Award Night Redemption)
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Penebusan kamar gratis berbasis poin dengan penyesuaian okupansi dinamis dan proteksi batas bawah properti.
                </p>
            </div>
            <a href="{{ route('hotel.travel_pass.dashboard') }}"
               class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-300 dark:hover:bg-slate-700 transition text-xs flex items-center space-x-1.5 self-start">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Kembali ke Dasbor</span>
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- Interactive Simulator & Pricing Card -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Controls & Property Picker -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>Konfigurasi & Simulasi Harga Award Night</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Pilih Properti Tujuan</label>
                        <select x-model="selectedProperty" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                            <option value="PROP-BALI-01">Grand Nusantara Resort Bali (Nusa Dua)</option>
                            <option value="PROP-LBN-02">Komodo Cliff Luxury Suites (Labuan Bajo)</option>
                            <option value="PROP-JKT-03">The Sultan Palace Metropolis (Jakarta)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tipe Kamar Reward</label>
                        <select x-model="selectedRoom" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                            <option value="DELUXE_OCEAN">Deluxe Ocean View (Base 15.000 PTS)</option>
                            <option value="VILLA_CLIFF">Private Pool Cliff Villa (Base 25.000 PTS)</option>
                            <option value="EXECUTIVE_SUITE">Executive Presidential Suite (Base 35.000 PTS)</option>
                        </select>
                    </div>
                </div>

                <!-- Occupancy Rate Slider Simulator -->
                <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-3">
                    <div class="flex justify-between items-center text-xs font-bold text-slate-800 dark:text-slate-200">
                        <span>Simulasi Tingkat Okupansi Hari Terpilih:</span>
                        <span class="text-indigo-600 dark:text-indigo-400 text-sm" x-text="Math.round(occupancyRate * 100) + '% Okupansi'"></span>
                    </div>
                    <input type="range" x-model.number="occupancyRate" min="0.10" max="1.00" step="0.05"
                           class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                    <div class="flex justify-between text-[11px] text-slate-400">
                        <span>Low / Off-Peak (10%)</span>
                        <span>Normal (70%)</span>
                        <span>Peak / Sold-Out (100%)</span>
                    </div>
                </div>

                <!-- Tier Selector -->
                <div>
                    <label class="block font-semibold text-xs text-slate-700 dark:text-slate-300 mb-2">Tingkat Keanggotaan Tamu (Tier Benefit Discount):</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                        <button type="button" @click="memberTier = 'SILVER'"
                                :class="memberTier === 'SILVER' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                class="py-2 px-3 rounded-xl transition text-center border border-transparent">
                            Silver (0%)
                        </button>
                        <button type="button" @click="memberTier = 'GOLD'"
                                :class="memberTier === 'GOLD' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                class="py-2 px-3 rounded-xl transition text-center border border-transparent">
                            Gold (5%)
                        </button>
                        <button type="button" @click="memberTier = 'PLATINUM'"
                                :class="memberTier === 'PLATINUM' ? 'bg-cyan-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                class="py-2 px-3 rounded-xl transition text-center border border-transparent">
                            Platinum (10%)
                        </button>
                        <button type="button" @click="memberTier = 'BLACK'"
                                :class="memberTier === 'BLACK' ? 'bg-zinc-900 text-amber-300 font-bold border-amber-500/50' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                class="py-2 px-3 rounded-xl transition text-center border">
                            Black (15%)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right 1 Col: Live Quote Box -->
            <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-6 shadow-xl border border-indigo-800 flex flex-col justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-300 block mb-1">Hasil Perhitungan Real-Time</span>
                    <div class="text-3xl font-black text-amber-300 mb-1" x-text="finalPoints.toLocaleString() + ' PTS'"></div>
                    <div class="text-xs text-indigo-200">
                        Ekuivalen Reimbursement: <strong x-text="'Rp ' + (finalPoints * 100).toLocaleString()"></strong>
                    </div>

                    <div class="mt-6 pt-5 border-t border-indigo-800/60 space-y-3 text-xs">
                        <div class="flex justify-between text-indigo-200">
                            <span>Biaya Marjinal Properti:</span>
                            <span class="font-mono">Rp 300.000</span>
                        </div>
                        <div class="flex justify-between text-indigo-200">
                            <span>Batas Bawah Floor 115%:</span>
                            <span class="font-mono text-emerald-400">Rp 345.000 (3.450 PTS)</span>
                        </div>
                        <div class="flex justify-between text-indigo-200">
                            <span>Status Proteksi Floor:</span>
                            <span class="font-bold" :class="isFloorGuaranteed ? 'text-amber-400' : 'text-emerald-400'"
                                  x-text="isFloorGuaranteed ? 'FLOOR APPLIED' : 'MARKET DEMAND'"></span>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="button" class="w-full py-3 bg-amber-400 hover:bg-amber-300 text-slate-950 font-black rounded-xl text-xs shadow-lg transition tracking-wide uppercase">
                        Konfirmasi Penukaran Kamar
                    </button>
                    <p class="text-[10px] text-indigo-300 text-center mt-2">
                        Pembayaran dijamin idempoten via double-entry ledger.
                    </p>
                </div>
            </div>
        </div>

        <!-- 14-Day Calendar Heatmap -->
        <x-hotel::award_calendar_heatmap
            propertyReference="PROP-BALI-01"
            :basePoints="15000"
            :marginalCostIdr="300000" />
    </div>
</div>
@endsection

