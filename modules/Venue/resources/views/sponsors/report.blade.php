@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="sponsorReportDashboard()">
    {{-- P-UI 5: Toast Notifications --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Header --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        Sponsorship Exposure Ledger V8
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        Turnstile Footfall Telemetry
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Laporan Aktivasi Sponsor & Audit Eksposur KPI
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Valuasi eksposur layar LED panggung utama, dwell time pengunjung, dan kelayakan pembayaran milestone kontrak (D90-7).
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="openValuateModal = true" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-600/30 transition">
                    📊 Valuasi Milestone Sponsor (V8)
                </button>
            </div>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">✕</button>
            </div>
        @endif

        {{-- Executive KPI Metrics --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Nilai Kontrak Sponsor</p>
                <h3 class="text-2xl font-black text-emerald-400 mt-2">
                    IDR {{ number_format($totalContractValueIdr) }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">Akrual piutang & pendapatan aktif</p>
            </div>

            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Impresi Terkirim</p>
                <h3 class="text-2xl font-black text-cyan-400 mt-2">
                    {{ number_format($totalDeliveredImpressions) }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">Turnstile footfall terbobot</p>
            </div>

            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tingkat Capaian KPI</p>
                <h3 class="text-2xl font-black text-amber-400 mt-2">
                    108.0%
                </h3>
                <p class="text-xs text-slate-400 mt-1">Memenuhi syarat 100% payout termin</p>
            </div>

            <div class="bg-slate-800/80 rounded-2xl p-6 border border-slate-700/60 shadow-xl">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Hak Eksklusivitas</p>
                <h3 class="text-2xl font-black text-purple-400 mt-2">
                    Terkunci (0 Pelanggaran)
                </h3>
                <p class="text-xs text-slate-400 mt-1">Kategori minuman premium terjaga</p>
            </div>
        </div>

        {{-- Table of Sponsor Deals & Milestones --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Kesepakatan Sponsor & Termin Pembayaran KPI</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $deals->total() }} Brand Mitra
                    </span>
                </h3>
            </div>

            {{-- P-UI 2: Empty State --}}
            @if($deals->isEmpty())
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500 border border-slate-700">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-300">Belum Ada Kontrak Sponsor Terdaftar</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        Daftarkan kontrak aktivasi brand mitra untuk memantau eksposur layar LED dan pelunasan termin milestone.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">Nama Sponsor</th>
                                <th scope="col" class="py-3 px-4">Kategori Brand</th>
                                <th scope="col" class="py-3 px-4">Nilai Kontrak IDR</th>
                                <th scope="col" class="py-3 px-4">Target vs Terkirim</th>
                                <th scope="col" class="py-3 px-4">Tarif CPM</th>
                                <th scope="col" class="py-3 px-4">Rincian Termin Milestone</th>
                                <th scope="col" class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($deals as $deal)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3.5 px-4 font-bold text-white">
                                        {{ $deal->sponsor_name }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-amber-300">
                                            {{ $deal->brand_category }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-emerald-400">
                                        IDR {{ number_format($deal->contract_value_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-300">
                                        {{ number_format($deal->delivered_impressions) }} / <span class="text-slate-400">{{ number_format($deal->target_impressions) }}</span>
                                        {{-- P-UI 7: Progressive State --}}
                                        <div class="w-full bg-slate-700 rounded-full h-1.5 mt-1">
                                            <div class="bg-cyan-400 h-1.5 rounded-full" style="width: {{ min(100, $deal->target_impressions > 0 ? ($deal->delivered_impressions / $deal->target_impressions) * 100 : 0) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-400">
                                        IDR {{ number_format($deal->cpm_rate_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1">
                                            @foreach($deal->milestones as $ms)
                                                <div class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-900/60 border border-slate-800">
                                                    <span class="text-slate-300">{{ $ms->milestone_name }}</span>
                                                    <div class="space-x-2">
                                                        <span class="text-amber-400 font-semibold">{{ $ms->payout_ratio_bps / 100 }}%</span>
                                                        <span class="text-emerald-400 font-bold">IDR {{ number_format($ms->milestone_payout_idr) }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            ● {{ ucfirst($deal->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $deals->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Analisis Return on Sponsorship Investment (ROSI) & Brand Sentiment (D90-7) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Box 1: Kalkulator ROSI Sponsor --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Kalkulator Return on Sponsorship (ROSI)</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300 font-semibold">ROSI 3.4x</span>
                </div>
                <p class="text-xs text-slate-400">
                    Estimasi nilai media yang diperoleh (*Earned Media Value*) dibandingkan dengan nilai kontrak sponsor tunai yang diinvestasikan.
                </p>

                <div class="space-y-3 pt-2 text-xs">
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Media Value Layar LED Utama:</span>
                            <div class="text-[10px] text-slate-400">120 slot tayang $\times$ 54.000 impresi terbobot</div>
                        </div>
                        <span class="font-mono text-emerald-400 font-bold">IDR 324.000.000</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Branding Gelang Wristband RFID:</span>
                            <div class="text-[10px] text-slate-400">Logo brand pada 3.000 gelang festival multi-hari</div>
                        </div>
                        <span class="font-mono text-cyan-400 font-bold">IDR 45.000.000</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-white">Social Media Co-Branding Exposure:</span>
                            <div class="text-[10px] text-slate-400">Tagar resmi acara & tayangan live streaming VIP</div>
                        </div>
                        <span class="font-mono text-purple-400 font-bold">IDR 85.000.000</span>
                    </div>
                </div>
            </div>

            {{-- Box 2: Hak Eksklusivitas Kategori & Sengketa Pelanggaran --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Penegakan Hak Eksklusivitas Brand</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs bg-cyan-500/20 text-cyan-300 font-semibold">Strict Exclusivity</span>
                </div>
                <p class="text-xs text-slate-400">
                    Sistem secara otomatis memvalidasi bahwa tidak ada brand kompetitor dalam kategori yang sama yang terdaftar pada acara yang sama.
                </p>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Kategori Terkunci Eksklusif:</span>
                        <span class="font-bold text-white">Minuman Energi Premium & Spirit Vodka</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Periode Perlindungan:</span>
                        <span class="font-bold text-emerald-400">H-30 hingga H+7 Pasca-Acara</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Klausul Ganti Rugi Pelanggaran:</span>
                        <span class="font-bold text-rose-400">Pinalti 150% dari Nilai Kontrak</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700 text-slate-400">
                        *Bila tenant atau booth eksternal tertangkap menjual produk kompetitor yang melanggar hak eksklusivitas, manajer venue berhak menyita produk dan membekukan operasional booth tenant tersebut seketika.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Modal Valuasi Milestone V8 --}}
    <div x-show="openValuateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openValuateModal = false"></div>
            <div class="inline-block w-full max-w-xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Valuasi Eksposur Sponsor (V8 Valuator)</h3>
                        <p class="text-xs text-slate-400">Footfall zona terbobot dan rasio pencairan milestone otomatis.</p>
                    </div>
                    <button type="button" @click="openValuateModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form method="POST" action="{{ route('venue.economy.sponsors.milestones.valuate') }}" class="space-y-4 py-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Milestone ID Target</label>
                        <input type="number" name="milestone_id" x-model="form.milestone_id" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Target Impresi Kontrak</label>
                            <input type="number" name="target_impressions" x-model="form.target_impressions" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Tarif CPM Ekuivalen IDR</label>
                            <input type="number" name="cpm_rate_idr" x-model="form.cpm_rate" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300">Anggaran Termin Milestone (IDR)</label>
                        <input type="number" name="milestone_budget_idr" x-model="form.budget_idr" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                    </div>

                    {{-- Data Pengiriman Zona --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Footfall Zona & Bobot Eksposur</label>
                        <div class="space-y-2">
                            <template x-for="(z, idx) in form.zones" :key="idx">
                                <div class="grid grid-cols-12 gap-2 bg-slate-800/80 p-2.5 rounded-xl border border-slate-700 items-center">
                                    <div class="col-span-5">
                                        <input type="text" :name="`zone_deliveries[${idx}][zone_name]`" x-model="z.name" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="number" :name="`zone_deliveries[${idx}][footfall]`" x-model="z.footfall" placeholder="Footfall" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" :name="`zone_deliveries[${idx}][dwell_weight_bps]`" x-model="z.dwell_bps" placeholder="Dwell bps" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                        <input type="hidden" :name="`zone_deliveries[${idx}][screen_share_bps]`" value="10000">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Live Valuation Summary --}}
                    <div class="p-4 bg-slate-800/80 rounded-xl border border-emerald-500/30 space-y-1.5">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Perkiraan Impresi Terbobot:</span>
                            <span class="font-bold text-white" x-text="Number(calculatedImpressions()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Persentase Capaian Target:</span>
                            <span class="font-bold text-amber-400" x-text="achievementPct() + '%'"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Perkiraan Payout Termin:</span>
                            <span class="font-bold text-emerald-400" x-text="'IDR ' + Number(calculatedPayout()).toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="openValuateModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-600/30">
                            Simpan & Valuasi V8
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function sponsorReportDashboard() {
    return {
        openValuateModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        form: {
            milestone_id: 1,
            target_impressions: 50000,
            cpm_rate: 60000,
            budget_idr: 100000000,
            zones: [
                { name: 'Main Stage LED', footfall: 40000, dwell_bps: 10000 },
                { name: 'Beachfront Entrance', footfall: 15000, dwell_bps: 6000 },
                { name: 'VIP Lounge Screens', footfall: 2000, dwell_bps: 25000 }
            ]
        },
        calculatedImpressions() {
            return this.form.zones.reduce((sum, z) => {
                const ff = Number(z.footfall || 0);
                const dw = Number(z.dwell_bps || 10000);
                return sum + Math.floor((ff * dw) / 10000);
            }, 0);
        },
        achievementPct() {
            const imp = this.calculatedImpressions();
            const target = Number(this.form.target_impressions || 1);
            return ((imp / target) * 100).toFixed(1);
        },
        calculatedPayout() {
            const ach = Number(this.achievementPct());
            const budget = Number(this.form.budget_idr || 0);
            if (ach >= 100) return budget;
            if (ach >= 70) return Math.floor((budget * ach) / 100);
            return 0;
        }
    };
}
</script>
@endsection
