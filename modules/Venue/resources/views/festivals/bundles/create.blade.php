@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="festivalBundleDashboard()">
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
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                        Multi-Vendor Festival Bundle V6
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        IFRS 15 / PSAK 72 Compliant
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Perancang Paket Bundling Festival & Alokasi SSP
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Alokasi matematis Standalone Selling Price (Hamilton-Hare), integrasi hotel mitra (G7-K16), dan F&B credit (D90-5).
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="openBundleModal = true" class="px-4 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-cyan-600/30 transition">
                    + Buat Pesanan Paket Bundle
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

        {{-- Table Orders & Multi-Vendor Allocations --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Daftar Pesanan Bundle Festival & Alokasi Vendor</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $orders->total() }} Pesanan
                    </span>
                </h3>
            </div>

            {{-- P-UI 2: Empty State --}}
            @if($orders->isEmpty())
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500 border border-slate-700">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-300">Belum Ada Pesanan Bundle Festival</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        Buat pesanan bundling festival baru untuk menguji alokasi proporsional SSP Hamilton-Hare ke mitra hotel dan F&B.
                    </p>
                    <button type="button" @click="openBundleModal = true" class="mt-4 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-semibold transition">
                        Buka Form Alokasi Bundle
                    </button>
                </div>
            @else
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">No. Order</th>
                                <th scope="col" class="py-3 px-4">Kode Bundle</th>
                                <th scope="col" class="py-3 px-4">Harga Paket IDR</th>
                                <th scope="col" class="py-3 px-4">Rincian Komponen Alokasi SSP</th>
                                <th scope="col" class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($orders as $ord)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3.5 px-4 font-mono font-bold text-cyan-300">
                                        {{ $ord->order_number }}
                                    </td>
                                    <td class="py-3.5 px-4 font-medium text-white">
                                        {{ $ord->bundle_code }}
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-emerald-400">
                                        IDR {{ number_format($ord->bundle_price_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1">
                                            @foreach($ord->allocations as $alloc)
                                                <div class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-900/60 border border-slate-800">
                                                    <span class="text-slate-300 font-semibold">{{ $alloc->component_name }} ({{ $alloc->vendor_code }})</span>
                                                    <div class="space-x-2 text-right">
                                                        <span class="text-amber-400">Alloc: IDR {{ number_format($alloc->allocated_amount_idr) }}</span>
                                                        <span class="text-rose-400 text-[10px]">Fee: {{ number_format($alloc->platform_fee_idr) }}</span>
                                                        <span class="text-emerald-400 font-bold">Net: {{ number_format($alloc->vendor_net_idr) }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        {{-- P-UI 8: Active Indicator --}}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            ● {{ ucfirst($ord->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Multi-Stage Clash Planner & RFID Cashless Token System (D90-5) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Box 1: Multi-Stage Clash Planner --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Multi-Stage Clash Planner</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-cyan-500/20 text-cyan-300 font-semibold">Stage Sync</span>
                    </h3>
                    <span class="text-xs text-emerald-400 font-bold">0 Bentrok Jadwal</span>
                </div>
                <p class="text-xs text-slate-400">
                    Mencegah penumpukan kerumunan berlebihan dengan mengatur jeda 30 menit antara penampil utama di Main Stage dan Beachfront Stage.
                </p>

                <div class="space-y-3 pt-2">
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white">Main Ocean Stage:</span>
                            <span class="text-slate-300 ml-1">DJ Martin Coast (EDM Live)</span>
                        </div>
                        <span class="font-mono text-amber-400">22:00 - 23:30</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white">Sunset Pool Deck:</span>
                            <span class="text-slate-300 ml-1">Deep House Acoustic Set</span>
                        </div>
                        <span class="font-mono text-cyan-400">19:30 - 21:30</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white">VIP Beachfront Lounge:</span>
                            <span class="text-slate-300 ml-1">Saxophone Sunset Jam</span>
                        </div>
                        <span class="font-mono text-purple-400">17:00 - 19:00</span>
                    </div>
                </div>
            </div>

            {{-- Box 2: Gelang RFID / NFC Cashless & Refund Saldo --}}
            <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Gelang Cashless RFID & Rekonsiliasi Saldo</span>
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300 font-semibold">Auto-Refundable</span>
                </div>
                <p class="text-xs text-slate-400">
                    Setiap tiket bundle mencakup gelang wristband RFID multi-hari dengan saldo F&B pra-isi dan stasiun top-up daring/luring.
                </p>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Saldo Cashless Diterbitkan:</span>
                        <span class="font-bold text-white">IDR 850.000.000</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Realisasi Belanja Tenant / Bar:</span>
                        <span class="font-bold text-emerald-400">IDR 712.400.000</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Sisa Saldo Belum Ditukar:</span>
                        <span class="font-bold text-amber-400">IDR 137.600.000</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700 text-slate-400">
                        *Sisa saldo wristband pasca-acara dapat diklaim pengembalian (auto-refund) via portal tamu atau ditransfer ke poin keanggotaan Beach Club dengan bonus 10% loyalty PTS.
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Alur Kompensasi & Rekonsiliasi Multi-Vendor Tenant (D90-6 & D90-8) --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-4">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Penyelesaian Multi-Vendor Tenant F&B & Hotel Glamping</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-purple-500/20 text-purple-300 font-semibold">G7-K16 & G7-K17</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Protokol pembagian dana escrow festival unearned revenue ke rekening escrow masing-masing vendor mitra.
                    </p>
                </div>
                <span class="text-xs font-mono text-emerald-400">Status Escrow: 100% Zero Discrepancy</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-amber-400 uppercase">Mitra Hotel & Glamping (K16)</span>
                    <p class="text-slate-400">Penguncian inventaris kamar otomatis via RoomBlockReserverService. Pelunasan dicairkan setelah status check-in terkonfirmasi oleh PMS.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">Plafon Potongan Fee:</span>
                        <span class="text-white font-bold">500 bps (5.0%)</span>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-cyan-400 uppercase">Tenant Kuliner Pesisir (K17)</span>
                    <p class="text-slate-400">Pencatatan pesanan F&B langsung dari modul Resto via OutletSaleRecorder. Settlement harian otomatis memotong komisi venue 10%.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">Jadwal Pencairan:</span>
                        <span class="text-white font-bold">H+1 Setiap Jam 10.00</span>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="font-bold text-purple-400 uppercase">Shuttle Bus & VIP Escort</span>
                    <p class="text-slate-400">Allotment kursi bus bandara dan pelacakan GPS armada secara real-time. Saldo dicairkan berbasis barcode boarding penumpang.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between font-mono">
                        <span class="text-slate-400">Jaminan Layanan SLA:</span>
                        <span class="text-white font-bold">Maksimal Tunggu 15 Mnt</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Modal Buat Order Bundle & Kalkulasi SSP V6 --}}
    <div x-show="openBundleModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openBundleModal = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Alokasi Paket Bundle Festival (Algoritma V6)</h3>
                        <p class="text-xs text-slate-400">Hamilton-Hare Largest Remainder Method tanpa deviasi pembulatan 1 rupiah pun.</p>
                    </div>
                    <button type="button" @click="openBundleModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form method="POST" action="{{ route('venue.economy.bundles.allocate') }}" class="space-y-4 py-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Pilih Acara Festival</label>
                        <select name="event_id" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}">{{ $ev->title }} ({{ $ev->event_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Kode Paket</label>
                            <input type="text" name="bundle_code" x-model="form.bundle_code" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Harga Jual Paket (IDR)</label>
                            <input type="number" name="bundle_price_idr" x-model="form.bundle_price" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Fee Platform (bps)</label>
                            <input type="number" name="platform_fee_bps" x-model="form.fee_bps" required placeholder="500 = 5%" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0" max="10000">
                        </div>
                    </div>

                    {{-- Komponen Multi-Vendor SSP --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Komponen Standalone Selling Price (SSP)</label>
                        <div class="space-y-2">
                            <template x-for="(comp, idx) in form.components" :key="idx">
                                <div class="grid grid-cols-12 gap-2 items-center bg-slate-800/80 p-2.5 rounded-xl border border-slate-700">
                                    <div class="col-span-5">
                                        <input type="text" :name="`components[${idx}][name]`" x-model="comp.name" placeholder="Nama Komponen" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="text" :name="`components[${idx}][vendor_id]`" x-model="comp.vendor_id" placeholder="Vendor ID" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="number" :name="`components[${idx}][ssp_idr]`" x-model="comp.ssp_idr" placeholder="Nilai SSP IDR" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white" min="1">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Live Calculation Summary Box --}}
                    <div class="p-4 bg-slate-800/80 rounded-xl border border-cyan-500/40 space-y-1.5">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Total Standalone Selling Price (Total SSP):</span>
                            <span class="font-bold text-white" x-text="'IDR ' + Number(totalSsp()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Diskon Bundling Efektif:</span>
                            <span class="font-bold text-amber-400" x-text="effectiveDiscountPct() + '%'"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Konservasi Jumlah Alokasi:</span>
                            <span class="font-bold text-emerald-400" x-text="'IDR ' + Number(form.bundle_price).toLocaleString('id-ID') + ' (Tepat 100%)'"></span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="openBundleModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-cyan-600/30">
                            Simpan & Alokasikan via V6
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function festivalBundleDashboard() {
    return {
        openBundleModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        form: {
            bundle_code: 'FEST-GLAMP-VIP-2026',
            bundle_price: 2999999,
            fee_bps: 500,
            components: [
                { name: '3-Day Festival Pass', vendor_id: 'VEN-ATLAS', ssp_idr: 1800000 },
                { name: 'Glamping Hotel 1-Night (K16)', vendor_id: 'HOTEL-MARINA', ssp_idr: 1500000 },
                { name: 'F&B Credit Beachfront (K17)', vendor_id: 'RESTO-BEACH', ssp_idr: 400000 },
                { name: 'VIP Shuttle Airport Allotment', vendor_id: 'SHUTTLE-BALI', ssp_idr: 300000 }
            ]
        },
        totalSsp() {
            return this.form.components.reduce((sum, item) => sum + Number(item.ssp_idr || 0), 0);
        },
        effectiveDiscountPct() {
            const total = this.totalSsp();
            const price = Number(this.form.bundle_price || 0);
            if (total <= 0 || price >= total) return '0.0';
            return (((total - price) / total) * 100).toFixed(1);
        }
    };
}
</script>
@endsection
