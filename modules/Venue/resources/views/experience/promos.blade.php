@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="venuePersonalizedPromosApp()">
    {{-- Toast --}}
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
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '🎁' : 'ℹ️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-purple-500/20 text-purple-400 border border-purple-500/30">
                        Personalization Engine & Hook Promo On-Site
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 117.5 • Algoritma V16 Certified</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Pemberian Promo Personal On-Site</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pengiriman voucher instan berdasarkan dwell-time zona VIP, batas bawah margin kotor COGS (margin floor guardrail), dan proteksi anti-spam ganda.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.experience.wps') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Layanan WPS Bar
                </a>
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Voucher Terkirim Malam Ini</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">184</span>
                <span class="text-[11px] text-purple-400 font-medium mt-1 block">Tamu VIP & Diamond Member</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Tingkat Konversi POS</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">42.8%</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">79 Promo Ditebus di Bar</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Margin Kotor Terjaga</span>
                <span class="text-2xl sm:text-3xl font-black text-sky-400 font-mono mt-2 block">&ge; 30%</span>
                <span class="text-[11px] text-sky-400 font-medium mt-1 block">Guardrail COGS Terpenuhi</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Pencegahan Spam Dobel</span>
                <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-2 block">100%</span>
                <span class="text-[11px] text-amber-400 font-medium mt-1 block">Cooldown 30 Menit Aktif</span>
            </div>
        </div>

        {{-- Active Promos Dispatch Table --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
            <div class="p-4 sm:p-5 border-b border-slate-700/80 flex items-center justify-between">
                <h3 class="font-bold text-white text-base">Antrean Penawaran Promo Berdasarkan Konteks Lokasi Tamu</h3>
                <button type="button" @click="dispatchSampleOffer()" class="px-3.5 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 text-white rounded-lg text-xs font-bold transition-all shadow-md">
                    + Picu Dispatch Penawaran Baru
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[11px] font-mono tracking-wider border-b border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4">Kode Voucher</th>
                            <th class="py-3.5 px-4">Tamu / Member</th>
                            <th class="py-3.5 px-4">Zona Saat Ini</th>
                            <th class="py-3.5 px-4">Diskon Promo</th>
                            <th class="py-3.5 px-4">Margin Kotor</th>
                            <th class="py-3.5 px-4">Masa Berlaku</th>
                            <th class="py-3.5 px-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60 font-sans">
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="py-4 px-4 font-mono font-bold text-purple-400">VIP-101-7A8B9C</td>
                            <td class="py-4 px-4 text-white font-medium">Michael Tanaka (Diamond VIP)</td>
                            <td class="py-4 px-4 text-slate-300">VIP Lounge Deck</td>
                            <td class="py-4 px-4 font-mono text-emerald-400 font-bold">25% OFF (Cocktail Premium)</td>
                            <td class="py-4 px-4 font-mono text-sky-400">38.5% (&gt; Floor 30%)</td>
                            <td class="py-4 px-4 text-slate-400 font-mono">Sisa 34 Menit</td>
                            <td class="py-4 px-4 text-right">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    TERKIRIM
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="py-4 px-4 font-mono font-bold text-purple-400">VIP-101-4F2E1D</td>
                            <td class="py-4 px-4 text-white font-medium">Siti Rahma (Gold Member)</td>
                            <td class="py-4 px-4 text-slate-300">Main Stage Front</td>
                            <td class="py-4 px-4 font-mono text-emerald-400 font-bold">15% OFF (Mineral & Energy)</td>
                            <td class="py-4 px-4 font-mono text-sky-400">45.0% (&gt; Floor 30%)</td>
                            <td class="py-4 px-4 text-slate-400 font-mono">Sisa 12 Menit</td>
                            <td class="py-4 px-4 text-right">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40">
                                    DITEBUS DI POS
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function venuePersonalizedPromosApp() {
    return {
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        showToast(type, title, message) {
            this.toast.type = type;
            this.toast.title = title;
            this.toast.message = message;
            this.toast.visible = true;
            setTimeout(() => { this.toast.visible = false; }, 4000);
        },
        dispatchSampleOffer() {
            this.showToast('success', 'Promo Terkirim Melalui Push Notification', 'Penawaran VIP 20% OFF terkirim ke tamu di Zona VIP. Margin aman.');
        }
    }
}
</script>
@endsection
