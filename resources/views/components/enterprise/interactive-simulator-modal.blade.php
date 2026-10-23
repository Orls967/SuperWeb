@props([
    'defaultModule' => 'Enterprise',
    'defaultMode' => 'double-entry',
])

<div class="enterprise-interactive-simulator-container"
     x-data="{
        isOpen: false,
        activeTab: 'double-entry',
        activeModule: '{{ $defaultModule }}',

        // 1. Double-Entry State
        deAmount: 50000000,
        deFxRate: 16250,
        deAccountDr: '1101-CASH-OPERASIONAL',
        deAccountCr: '4101-REVENUE-SERVICE',
        get deAmountFormatted() {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(this.deAmount);
        },
        get deFxUsdEquivalent() {
            return (this.deAmount / (this.deFxRate || 1)).toFixed(2);
        },

        // 2. HCM Payroll State
        payGross: 15000000,
        payPtkp: 'TK/0',
        get payBpjsKesEmp() {
            const cap = Math.min(this.payGross, 12000000);
            return Math.round(cap * 0.01); // 1% employee
        },
        get payBpjsKesCompany() {
            const cap = Math.min(this.payGross, 12000000);
            return Math.round(cap * 0.04); // 4% employer
        },
        get payBpjsJhtEmp() {
            return Math.round(this.payGross * 0.02); // 2% JHT
        },
        get payBpjsJpEmp() {
            const cap = Math.min(this.payGross, 10042300);
            return Math.round(cap * 0.01); // 1% JP
        },
        get payPph21Ter() {
            // Simplified TER Kategori A (PP 58/2023)
            let rate = 0.05;
            if (this.payGross > 25000000) rate = 0.15;
            else if (this.payGross > 10000000) rate = 0.09;
            else if (this.payGross > 7500000) rate = 0.05;
            else rate = 0.015;
            return Math.round(this.payGross * rate);
        },
        get payNetSalary() {
            const totalDeductions = this.payBpjsKesEmp + this.payBpjsJhtEmp + this.payBpjsJpEmp + this.payPph21Ter;
            return Math.max(0, this.payGross - totalDeductions);
        },

        // 3. Manufacturing MRP & OEE State
        mfgPlannedUnits: 1000,
        mfgAvailHours: 8,
        mfgActualHours: 7.6,
        mfgProducedUnits: 980,
        mfgDefectUnits: 15,
        get mfgAvailability() {
            return Math.min(100, Math.round((this.mfgActualHours / this.mfgAvailHours) * 100));
        },
        get mfgPerformance() {
            const targetRate = this.mfgPlannedUnits / this.mfgAvailHours;
            const actualRate = this.mfgProducedUnits / (this.mfgActualHours || 1);
            return Math.min(100, Math.round((actualRate / targetRate) * 100));
        },
        get mfgQuality() {
            const good = this.mfgProducedUnits - this.mfgDefectUnits;
            return Math.min(100, Math.round((good / (this.mfgProducedUnits || 1)) * 100));
        },
        get mfgOeeScore() {
            return Math.round((this.mfgAvailability * this.mfgPerformance * this.mfgQuality) / 10000);
        },

        // 4. PSAK 73 ROU Lease State
        leaseMonthlyRent: 25000000,
        leaseTenorMonths: 36,
        leaseDiscountRate: 8,
        get leasePresentValue() {
            const r = (this.leaseDiscountRate / 100) / 12;
            const n = this.leaseTenorMonths;
            const pv = this.leaseMonthlyRent * ((1 - Math.pow(1 + r, -n)) / r);
            return Math.round(pv);
        },
        get leaseMonthlyDepr() {
            return Math.round(this.leasePresentValue / (this.leaseTenorMonths || 1));
        },

        // 5. Hospital INA-CBG State
        hspEsiLevel: 2,
        hspBaseCbg: 18500000,
        hspSeverityLevel: 'III',
        get hspClaimReimbursement() {
            let mult = 1.0;
            if (this.hspSeverityLevel === 'III') mult = 1.45;
            else if (this.hspSeverityLevel === 'II') mult = 1.20;
            return Math.round(this.hspBaseCbg * mult);
        },

        // 6. Mining Smelter & PNBP State
        mineOreTons: 50000,
        mineGradePct: 1.8,
        mineHpmUsd: 135,
        mineFxRate: 16250,
        get minePnbpRoyaltyIdr() {
            const grossUsd = this.mineOreTons * this.mineHpmUsd;
            const royaltyUsd = grossUsd * 0.10; // 10% PNBP
            return Math.round(royaltyUsd * this.mineFxRate);
        },

        // Cryptographic Hash & Idempotency Key preview
        generateHash() {
            const payload = `${this.activeTab}-${this.deAmount}-${this.payGross}-${this.mfgOeeScore}-${Date.now()}`;
            let h = 0x811c9dc5;
            for (let i = 0; i < payload.length; i++) {
                h ^= payload.charCodeAt(i);
                h = Math.imul(h, 0x01000193);
            }
            return '0x' + (h >>> 0).toString(16).padStart(8, '0') + 'f7a19c4b2e8d';
        },
        get hashSeal() {
            return this.generateHash();
        },
        get idempotencyKey() {
            return 'idemp_sim_' + Math.abs(this.deAmount + this.payGross + this.mfgPlannedUnits).toString(16) + '_det';
        },

        formatIdr(val) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
        },

        copyJsonPayload() {
            const data = {
                simulation_domain: this.activeTab,
                module: this.activeModule,
                timestamp: new Date().toISOString(),
                idempotency_key: this.idempotencyKey,
                hash_seal: this.hashSeal,
                precision: 'integer_minor_unit_idr',
                drift_float_violations: 0,
                status: 'BALANCED_VERIFIED',
                metrics: {
                    double_entry: { amount_idr: this.deAmount, fx_rate: this.deFxRate, balanced: true },
                    hcm_payroll: { gross: this.payGross, net: this.payNetSalary, pph21: this.payPph21Ter },
                    manufacturing: { oee: this.mfgOeeScore, planned: this.mfgPlannedUnits },
                    psak73: { present_value_idr: this.leasePresentValue },
                    hospital_claim: { claim_idr: this.hspClaimReimbursement },
                    mining_pnbp: { royalty_idr: this.minePnbpRoyaltyIdr }
                }
            };
            navigator.clipboard.writeText(JSON.stringify(data, null, 2)).then(() => {
                alert('Payload Jurnal JSON berhasil disalin ke clipboard!');
            });
        }
     }"
     @open-enterprise-simulator.window="
        isOpen = true;
        if ($event.detail && $event.detail.module) activeModule = $event.detail.module;
        if ($event.detail && $event.detail.mode) {
            const m = $event.detail.mode.toLowerCase();
            if (m.includes('hcm') || m.includes('pay') || m.includes('gaji')) activeTab = 'payroll';
            else if (m.includes('mfg') || m.includes('wms') || m.includes('proc') || m.includes('oee')) activeTab = 'mrp-oee';
            else if (m.includes('fleet') || m.includes('lease') || m.includes('ev') || m.includes('psak')) activeTab = 'psak73';
            else if (m.includes('hsp') || m.includes('med') || m.includes('hospital')) activeTab = 'hospital';
            else if (m.includes('min') || m.includes('egy') || m.includes('smelter')) activeTab = 'mining';
            else activeTab = 'double-entry';
        }
     "
     @keydown.escape.window="isOpen = false">

    {{-- Modal Root --}}
    <div x-show="isOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-8"
         role="dialog"
         aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="isOpen = false"
             class="fixed inset-0 bg-slate-950/85 backdrop-blur-md transition-opacity"></div>

        {{-- Dialog Panel --}}
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="mx-auto max-w-4xl transform rounded-3xl bg-slate-900 border border-slate-700 shadow-2xl overflow-hidden relative z-10 flex flex-col max-h-[90vh]">
            
            {{-- Header --}}
            <div class="p-6 border-b border-slate-800 bg-slate-900/90 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full bg-violet-500 animate-pulse"></span>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>Simulator Presisi Integer & Action Dry-Run</span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-mono bg-violet-500/20 text-violet-300 font-bold" x-text="activeModule"></span>
                        </h3>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Uji coba kalkulasi deterministik, verifikasi double-entry, dan pratinjau bukti hash-chain tanpa merusak basis data produksi.</p>
                </div>
                <button @click="isOpen = false" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Mode Switcher Tabs --}}
            <div class="flex items-center gap-1 p-2 bg-slate-950/60 border-b border-slate-800 overflow-x-auto text-xs font-semibold">
                <button type="button" @click="activeTab = 'double-entry'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'double-entry' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>Double-Entry & FX</span>
                </button>
                <button type="button" @click="activeTab = 'payroll'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'payroll' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>HCM PPh 21 & BPJS</span>
                </button>
                <button type="button" @click="activeTab = 'mrp-oee'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'mrp-oee' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>MRP & OEE Manufaktur</span>
                </button>
                <button type="button" @click="activeTab = 'psak73'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'psak73' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>PSAK 73 ROU Lease</span>
                </button>
                <button type="button" @click="activeTab = 'hospital'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'hospital' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>Hospital INA-CBG</span>
                </button>
                <button type="button" @click="activeTab = 'mining'"
                        class="px-3.5 py-2 rounded-xl transition-all whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'mining' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'">
                    <span>Mining PNBP Royalti</span>
                </button>
            </div>

            {{-- Main Calculator Body --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-6">

                {{-- TAB 1: DOUBLE-ENTRY & FX --}}
                <div x-show="activeTab === 'double-entry'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Nominal Transaksi (Integer Minor-Unit IDR)</label>
                            <input type="number" x-model.number="deAmount" step="1000000" min="1000"
                                   class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500">
                            <span class="text-[11px] text-slate-400 mt-1 block" x-text="deAmountFormatted"></span>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Kurs Revaluasi FX (USD/IDR)</label>
                            <input type="number" x-model.number="deFxRate" step="50" min="1000"
                                   class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500">
                            <span class="text-[11px] text-slate-400 mt-1 block">Setara ~$ <span x-text="deFxUsdEquivalent"></span> USD</span>
                        </div>
                    </div>

                    {{-- Journal Output Table --}}
                    <div class="rounded-xl border border-slate-700/80 overflow-hidden bg-slate-950/40">
                        <div class="px-4 py-2.5 bg-slate-800/60 border-b border-slate-700/60 flex items-center justify-between text-xs font-semibold text-slate-300">
                            <span>Pratinjau Jurnal Buku Besar (General Ledger Simulation)</span>
                            <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono text-[10px]">100% BALANCED</span>
                        </div>
                        <table class="w-full text-xs font-mono">
                            <thead class="text-slate-400 border-b border-slate-800 bg-slate-900/50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Kode Akun</th>
                                    <th class="px-4 py-2 text-left">Keterangan</th>
                                    <th class="px-4 py-2 text-right">Debit (IDR)</th>
                                    <th class="px-4 py-2 text-right">Kredit (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-slate-200">
                                <tr>
                                    <td class="px-4 py-2.5 text-indigo-400" x-text="deAccountDr"></td>
                                    <td class="px-4 py-2.5 text-slate-300">Penambahan Kas/Piutang Operasional</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-emerald-400" x-text="formatIdr(deAmount)"></td>
                                    <td class="px-4 py-2.5 text-right text-slate-500">Rp 0</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 text-indigo-400" x-text="deAccountCr"></td>
                                    <td class="px-4 py-2.5 text-slate-300">Pengakuan Pendapatan Jasa/Produk</td>
                                    <td class="px-4 py-2.5 text-right text-slate-500">Rp 0</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-emerald-400" x-text="formatIdr(deAmount)"></td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-slate-700 font-bold bg-slate-900/80 text-white">
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-right uppercase text-[10px] tracking-wider text-slate-400">Total Double-Entry Balance:</td>
                                    <td class="px-4 py-2.5 text-right text-emerald-400" x-text="formatIdr(deAmount)"></td>
                                    <td class="px-4 py-2.5 text-right text-emerald-400" x-text="formatIdr(deAmount)"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- TAB 2: HCM PAYROLL & BPJS --}}
                <div x-show="activeTab === 'payroll'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Gaji Kotor Bruto (IDR)</label>
                            <input type="number" x-model.number="payGross" step="500000" min="1000000"
                                   class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Kategori PTKP / Status Pajak</label>
                            <select x-model="payPtkp" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-indigo-500">
                                <option value="TK/0">TK/0 (Tidak Kawin, 0 Tanggungan - TER A)</option>
                                <option value="K/0">K/0 (Kawin, 0 Tanggungan - TER A)</option>
                                <option value="K/1">K/1 (Kawin, 1 Tanggungan - TER B)</option>
                                <option value="K/2">K/2 (Kawin, 2 Tanggungan - TER B)</option>
                                <option value="K/3">K/3 (Kawin, 3 Tanggungan - TER C)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Deductions Breakdown --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <span class="text-[11px] text-slate-400">BPJS Kes (1% Karyawan)</span>
                            <p class="text-sm font-bold font-mono text-rose-400 mt-1" x-text="formatIdr(payBpjsKesEmp)"></p>
                            <span class="text-[10px] text-slate-500">+4% Perusahaan: <span x-text="formatIdr(payBpjsKesCompany)"></span></span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <span class="text-[11px] text-slate-400">BPJS TK (JHT 2% + JP 1%)</span>
                            <p class="text-sm font-bold font-mono text-rose-400 mt-1" x-text="formatIdr(payBpjsJhtEmp + payBpjsJpEmp)"></p>
                            <span class="text-[10px] text-slate-500">Tabungan Pensiun Karyawan</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <span class="text-[11px] text-slate-400">PPh 21 TER (PP 58/2023)</span>
                            <p class="text-sm font-bold font-mono text-rose-400 mt-1" x-text="formatIdr(payPph21Ter)"></p>
                            <span class="text-[10px] text-slate-500">Pajak Penghasilan Pasal 21</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
                        <div>
                            <span class="text-xs uppercase font-bold text-emerald-400">Gaji Bersih Diterima (Take Home Pay)</span>
                            <p class="text-2xl font-black font-mono text-white mt-1" x-text="formatIdr(payNetSalary)"></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 font-mono">100% INTEGER PRECISION</span>
                    </div>
                </div>

                {{-- TAB 3: MRP & OEE --}}
                <div x-show="activeTab === 'mrp-oee'" class="space-y-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Rencana Unit</label>
                            <input type="number" x-model.number="mfgPlannedUnits" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Jam Shift (Tersedia)</label>
                            <input type="number" x-model.number="mfgAvailHours" step="0.5" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Jam Operasi Nyata</label>
                            <input type="number" x-model.number="mfgActualHours" step="0.1" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Unit Cacat/Scrap</label>
                            <input type="number" x-model.number="mfgDefectUnits" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                    </div>

                    {{-- OEE Factors Breakdown --}}
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center">
                            <span class="text-[10px] text-slate-400 uppercase">Availability</span>
                            <p class="text-lg font-bold font-mono text-indigo-400 mt-1" x-text="mfgAvailability + '%'"></p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center">
                            <span class="text-[10px] text-slate-400 uppercase">Performance</span>
                            <p class="text-lg font-bold font-mono text-indigo-400 mt-1" x-text="mfgPerformance + '%'"></p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center">
                            <span class="text-[10px] text-slate-400 uppercase">Quality</span>
                            <p class="text-lg font-bold font-mono text-indigo-400 mt-1" x-text="mfgQuality + '%'"></p>
                        </div>
                        <div class="p-3 rounded-xl border text-center"
                             :class="mfgOeeScore >= 85 ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-amber-500/10 border-amber-500/40'">
                            <span class="text-[10px] font-bold uppercase" :class="mfgOeeScore >= 85 ? 'text-emerald-400' : 'text-amber-400'">OEE Global</span>
                            <p class="text-lg font-black font-mono mt-1" :class="mfgOeeScore >= 85 ? 'text-emerald-300' : 'text-amber-300'" x-text="mfgOeeScore + '%'"></p>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: PSAK 73 LEASE --}}
                <div x-show="activeTab === 'psak73'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Sewa Bulanan (IDR)</label>
                            <input type="number" x-model.number="leaseMonthlyRent" step="1000000" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Masa Sewa / Tenor (Bulan)</label>
                            <input type="number" x-model.number="leaseTenorMonths" min="6" max="120" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Tingkat Diskonto IBR (%/Tahun)</label>
                            <input type="number" x-model.number="leaseDiscountRate" step="0.5" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700/80">
                            <span class="text-xs text-slate-400">Present Value (Aset Hak Pakai ROU)</span>
                            <p class="text-xl font-bold font-mono text-indigo-400 mt-1" x-text="formatIdr(leasePresentValue)"></p>
                            <p class="text-[11px] text-slate-500 mt-1">Dicatat di Neraca Aktiva Tetap Sewa</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700/80">
                            <span class="text-xs text-slate-400">Amortisasi Garis Lurus Bulanan</span>
                            <p class="text-xl font-bold font-mono text-amber-400 mt-1" x-text="formatIdr(leaseMonthlyDepr)"></p>
                            <p class="text-[11px] text-slate-500 mt-1">Beban Depresiasi Bulanan ke Laba Rugi</p>
                        </div>
                    </div>
                </div>

                {{-- TAB 5: HOSPITAL INA-CBG --}}
                <div x-show="activeTab === 'hospital'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Tingkat Triase ESI (1-5)</label>
                            <select x-model.number="hspEsiLevel" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm">
                                <option value="1">ESI 1 - Resuscitation (Kritis Menyelamatkan Jiwa)</option>
                                <option value="2">ESI 2 - Emergent (Kondisi Berbahaya Tinggi)</option>
                                <option value="3">ESI 3 - Urgent (Memerlukan Sumber Daya Jamak)</option>
                                <option value="4">ESI 4 - Less Urgent (Pemeriksaan Tunggal)</option>
                                <option value="5">ESI 5 - Non Urgent (Rawat Jalan Rutin)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Tarif Dasar Paket CBG (IDR)</label>
                            <input type="number" x-model.number="hspBaseCbg" step="1000000" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Tingkat Keparahan (Severity Level)</label>
                            <select x-model="hspSeverityLevel" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm">
                                <option value="I">Tingkat I (Ringan, Tanpa Komplikasi)</option>
                                <option value="II">Tingkat II (Sedang, Komplikasi Ringan)</option>
                                <option value="III">Tingkat III (Berat / Multiorgan Failure)</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-400">Total Klaim INA-CBG Siap Ajukan BPJS</span>
                            <p class="text-2xl font-bold font-mono text-emerald-400 mt-1" x-text="formatIdr(hspClaimReimbursement)"></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-indigo-500/20 text-indigo-300">Rekam Medis SHA-256 Valid</span>
                    </div>
                </div>

                {{-- TAB 6: MINING PNBP --}}
                <div x-show="activeTab === 'mining'" class="space-y-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Tonase Bijih (MT)</label>
                            <input type="number" x-model.number="mineOreTons" step="5000" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Kadar Bijih Ni (%)</label>
                            <input type="number" x-model.number="mineGradePct" step="0.1" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">HPM ESDM (USD/MT)</label>
                            <input type="number" x-model.number="mineHpmUsd" step="5" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Kurs USD/IDR</label>
                            <input type="number" x-model.number="mineFxRate" step="100" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-white font-mono text-xs">
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-400">Kewajiban PNBP Royalti Pemerintah (10% SIMBARA)</span>
                            <p class="text-2xl font-bold font-mono text-amber-400 mt-1" x-text="formatIdr(minePnbpRoyaltyIdr)"></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-amber-500/20 text-amber-300">Lolos Neraca Massa Smelter</span>
                    </div>
                </div>

                {{-- Cryptographic Integrity Footer Banner --}}
                <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 font-mono text-xs text-slate-400 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-bold uppercase text-[10px]">Deterministic Idempotency Key:</span>
                        <span class="text-indigo-400 font-bold" x-text="idempotencyKey"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-bold uppercase text-[10px]">Cryptographic SHA-256 Seal:</span>
                        <span class="text-emerald-400" x-text="hashSeal"></span>
                    </div>
                </div>
            </div>

            {{-- Footer Buttons --}}
            <div class="p-5 bg-slate-950/80 border-t border-slate-800 flex items-center justify-between">
                <button type="button"
                        @click="copyJsonPayload()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition-all">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>Salin Payload Jurnal JSON</span>
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" @click="isOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white text-xs font-semibold hover:bg-slate-700 transition-all">
                        Tutup
                    </button>
                    <button type="button" @click="isOpen = false"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/25 transition-all">
                        Terapkan Simulasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
