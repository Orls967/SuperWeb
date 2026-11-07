@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="personalizationAnalyticsApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-purple-500/20 text-purple-400 border border-purple-500/30">
                        Analitik Personalization Loop & Margin Floor
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 117.5 & 112.5 Feedback Engine</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Performa Konversi Promo & Efisiensi COGS</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pelacakan konversi voucher on-site terhadap lift omset F&B, kepatuhan batas bawah margin profit, dan retensi tamu VIP.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.experience.promos') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Dashboard Promo
                </a>
            </div>
        </div>

        {{-- Metrics Top Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Total Lift Pendapatan F&B</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">+Rp 42.500.000</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Inkremental dari Promo On-Site</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Rata-rata Margin Kotor</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">41.8%</span>
                <span class="text-[11px] text-sky-400 font-medium mt-1 block">Di Atas Floor Guardrail 30%</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Waktu Respons Tamu</span>
                <span class="text-2xl sm:text-3xl font-black text-purple-400 font-mono mt-2 block">14 <span class="text-xs font-normal text-slate-400">menit</span></span>
                <span class="text-[11px] text-purple-400 font-medium mt-1 block">Rata-rata Sejak Notifikasi Muncul</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Akurasi Rekomendasi V16</span>
                <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-2 block">94.2%</span>
                <span class="text-[11px] text-amber-400 font-medium mt-1 block">Kategori Minuman Sesuai Preferensi</span>
            </div>
        </div>

        {{-- Promotion Category Lift Table --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
            <div class="p-4 sm:p-5 border-b border-slate-700/80 flex items-center justify-between">
                <h3 class="font-bold text-white text-base">Rincian Efektivitas Promo per Kategori Menu</h3>
                <span class="text-xs font-mono text-emerald-400">COGS Guardrails Active</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[11px] font-mono tracking-wider border-b border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Kategori Produk</th>
                            <th class="py-3 px-4">Tawaran Terkirim</th>
                            <th class="py-3 px-4">Klaim Penebusan</th>
                            <th class="py-3 px-4">Rasio Konversi</th>
                            <th class="py-3 px-4">Rata-rata Margin COGS</th>
                            <th class="py-3 px-4 text-right">Perlindungan Guardrail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60 font-sans">
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">Signature Cocktails</td>
                            <td class="py-3.5 px-4 font-mono">110</td>
                            <td class="py-3.5 px-4 font-mono text-emerald-400 font-bold">58</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">52.7%</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">46.5%</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">SAFE (MARGIN &gt; 30%)</span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">Premium Spirits & Bottle Service</td>
                            <td class="py-3.5 px-4 font-mono">42</td>
                            <td class="py-3.5 px-4 font-mono text-emerald-400 font-bold">14</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-400">33.3%</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">38.0%</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">SAFE (MARGIN &gt; 30%)</span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">Non-Alcoholic & Hydration Pack</td>
                            <td class="py-3.5 px-4 font-mono">32</td>
                            <td class="py-3.5 px-4 font-mono text-emerald-400 font-bold">18</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">56.2%</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">55.0%</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">SAFE (MARGIN &gt; 30%)</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function personalizationAnalyticsApp() {
    return {
        activeFilter: 'ALL'
    }
}
</script>
@endsection
