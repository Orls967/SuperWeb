@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="crowdIncidentsApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Manajemen Insiden Kerumunan
                    </span>
                    <span class="text-xs text-slate-400 font-mono">State Machine: 6 State Terverifikasi</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Log Eskalasi & Triage Kerumunan</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pencatatan lonjakan densitas massa, mitigasi chokepoint, dan verifikasi status pemulihan gerbang normal (Fase 117.3).
                </p>
            </div>
            <div>
                <a href="{{ route('venue.crowd.dashboard') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Kembali ke Dashboard
                </a>
            </div>
        </div>

        {{-- Incident Table --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
            <div class="p-4 sm:p-5 border-b border-slate-700/80 flex items-center justify-between">
                <h3 class="font-bold text-white text-base">Daftar Insiden Aktif & Riwayat Eskalasi</h3>
                <span class="text-xs font-mono text-emerald-400">Total 3 Insiden Terdata</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[11px] font-mono tracking-wider border-b border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4">Kode Insiden</th>
                            <th class="py-3.5 px-4">Zona Terkait</th>
                            <th class="py-3.5 px-4">Densitas Tertinggi</th>
                            <th class="py-3.5 px-4">Tingkat Risiko</th>
                            <th class="py-3.5 px-4">Status Siklus</th>
                            <th class="py-3.5 px-4">Aksi Penanganan</th>
                            <th class="py-3.5 px-4 text-right">Opsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60 font-sans">
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="py-4 px-4 font-mono font-bold text-sky-400">CRUSH-101-1-STAGE</td>
                            <td class="py-4 px-4 text-white font-medium">Main Stage Pit</td>
                            <td class="py-4 px-4 font-mono text-rose-400 font-bold">4.8 org/m²</td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
                                    CRITICAL
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                    DISPATCHED
                                </span>
                            </td>
                            <td class="py-4 px-4 text-slate-300 max-w-xs truncate">
                                Paramedis dikerahkan; Gerbang EXIT 1 dibuka fail-safe.
                            </td>
                            <td class="py-4 px-4 text-right">
                                <button type="button" @click="openResolveModal(1, 'CRUSH-101-1-STAGE')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs transition-colors">
                                    Selesaikan &rarr;
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="py-4 px-4 font-mono font-bold text-sky-400">CRUSH-101-2-BAR</td>
                            <td class="py-4 px-4 text-white font-medium">Central Bar Pavilion</td>
                            <td class="py-4 px-4 font-mono text-amber-400 font-bold">3.2 org/m²</td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                    MODERATE
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    RESOLVED
                                </span>
                            </td>
                            <td class="py-4 px-4 text-slate-300 max-w-xs truncate">
                                4 bartender tambahan dialokasikan; waktu tunggu WPS turun ke 45 detik.
                            </td>
                            <td class="py-4 px-4 text-right">
                                <span class="text-xs text-slate-500 font-mono">Tuntas</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Resolve Modal --}}
        <div x-show="showResolveModal" 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             style="display: none;">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4" @click.away="showResolveModal = false">
                <h3 class="text-lg font-bold text-white">Selesaikan Insiden Kerumunan</h3>
                <p class="text-xs text-slate-400">Masukkan catatan investigasi petugas keselamatan sebelum mengembalikan gerbang ke ritme operasional normal.</p>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan Pemulihan</label>
                    <textarea x-model="resolveNotes" rows="3" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:border-emerald-500 focus:outline-none" placeholder="Densitas telah turun di bawah 2.5 org/m², situasi kondusif."></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showResolveModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-semibold">Batal</button>
                    <button type="button" @click="executeResolve()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold">Simpan & Selesaikan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function crowdIncidentsApp() {
    return {
        showResolveModal: false,
        activeIncidentId: null,
        activeIncidentCode: '',
        resolveNotes: '',
        openResolveModal(id, code) {
            this.activeIncidentId = id;
            this.activeIncidentCode = code;
            this.resolveNotes = '';
            this.showResolveModal = true;
        },
        executeResolve() {
            alert('Insiden ' + this.activeIncidentCode + ' telah berhasil ditandai selesai.');
            this.showResolveModal = false;
        }
    }
}
</script>
@endsection
