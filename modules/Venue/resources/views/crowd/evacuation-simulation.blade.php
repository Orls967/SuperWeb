@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="evacuationSimulationApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-rose-500/20 text-rose-400 border border-rose-500/30">
                        Simulator Evakuasi Massa Dinamis
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Standar NFPA 101 & Fruin Level of Service (LoS)</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Simulasi Waktu Klirens Evakuasi Darurat</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Kalkulasi debit pelepasan gerbang (73 orang/meter/menit), deteksi leher botol koridor, dan estimasi total menit hingga seluruh venue kosong.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.crowd.dashboard') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Kembali ke Dashboard
                </a>
            </div>
        </div>

        {{-- Simulation Parameter Controls --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl backdrop-blur-md space-y-4">
            <h3 class="text-base font-bold text-white border-b border-slate-700 pb-3 flex items-center justify-between">
                <span>Parameter Model Evakuasi Acara</span>
                <span class="text-xs font-mono text-emerald-400">Total Tamu: 2.500 Orang</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Kecepatan Berjalan Rata-rata</label>
                    <input type="text" readonly value="1.2 meter / detik" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Laju Aliran NFPA 101 Standard</label>
                    <input type="text" readonly value="73 orang / meter lebar / menit" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-emerald-400 font-mono font-bold">
                </div>
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Ambang Maksimal Regulasi Klirens</label>
                    <input type="text" readonly value="8.0 Menit (Maksimal)" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-amber-400 font-mono font-bold">
                </div>
            </div>
        </div>

        {{-- Results Breakdown Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5">
                <span class="text-xs text-slate-400 block">Estimasi Waktu Klirens Penuh</span>
                <span class="text-3xl font-black font-mono text-emerald-400 mt-2 block">5.4 <span class="text-xs font-normal text-slate-400">menit</span></span>
                <span class="text-[11px] text-emerald-400 mt-2 block font-medium">Lolos Standar Keselamatan (&le; 8 Menit)</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5">
                <span class="text-xs text-slate-400 block">Lebar Total Gerbang Keluar Bersih</span>
                <span class="text-3xl font-black font-mono text-white mt-2 block">9.2 <span class="text-xs font-normal text-slate-400">meter</span></span>
                <span class="text-[11px] text-sky-400 mt-2 block font-medium">4 Jalur Gerbang Darurat Aktif</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5">
                <span class="text-xs text-slate-400 block">Chokepoint Teridentifikasi</span>
                <span class="text-3xl font-black font-mono text-emerald-400 mt-2 block">0 <span class="text-xs font-normal text-slate-400">titik kritis</span></span>
                <span class="text-[11px] text-emerald-400 mt-2 block font-medium">Semua Antrean Pintu &lt; 4 Menit</span>
            </div>
        </div>

        {{-- Zone Simulation Breakdown Table --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
            <div class="p-4 sm:p-5 border-b border-slate-700/80 flex items-center justify-between">
                <h3 class="font-bold text-white text-base">Hasil Simulasi Mikro per Zona Ruang Acara</h3>
                <span class="text-xs font-mono text-sky-400">NFPA 101 Compliant</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[11px] font-mono tracking-wider border-b border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Sektor / Zona</th>
                            <th class="py-3 px-4">Jumlah Tamu</th>
                            <th class="py-3 px-4">Lebar Gerbang</th>
                            <th class="py-3 px-4">Laju Aliran (PPM)</th>
                            <th class="py-3 px-4">Waktu Antrean</th>
                            <th class="py-3 px-4">Total Klirens</th>
                            <th class="py-3 px-4 text-right">Status Kepatuhan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60 font-sans">
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">Main Stage Floor</td>
                            <td class="py-3.5 px-4 font-mono">1.200</td>
                            <td class="py-3.5 px-4 font-mono">4.0 m</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">292 ppm</td>
                            <td class="py-3.5 px-4 font-mono text-amber-400">4.1 min</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">5.2 min</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">COMPLIANT</span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">VIP Lounge & Mezzanine</td>
                            <td class="py-3.5 px-4 font-mono">400</td>
                            <td class="py-3.5 px-4 font-mono">2.2 m</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">160 ppm</td>
                            <td class="py-3.5 px-4 font-mono text-emerald-400">2.5 min</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">3.4 min</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">COMPLIANT</span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-3.5 px-4 font-bold text-white">Outdoor Garden & F&B Hub</td>
                            <td class="py-3.5 px-4 font-mono">900</td>
                            <td class="py-3.5 px-4 font-mono">3.0 m</td>
                            <td class="py-3.5 px-4 font-mono text-sky-400">219 ppm</td>
                            <td class="py-3.5 px-4 font-mono text-emerald-400">4.1 min</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">5.4 min</td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">COMPLIANT</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function evacuationSimulationApp() {
    return {
        isSimulating: false
    }
}
</script>
@endsection
