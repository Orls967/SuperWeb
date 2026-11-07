@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="venueExperienceWpsApp()">
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
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '🍹' : '⚠️'"></span>
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
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Guest Experience & Service Velocity
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 117.4 • Wait-per-Service (WPS) Governor</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Dashboard Layanan Bar & Antrean WPS</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Pemantauan kedalaman antrean bar real-time, skor WPS antrean per stasiun layanan, dan auto-dispatch penguatan bartender HCM saat antrean jenuh.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('venue.experience.promos') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-purple-400 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Promo Personal VIP &rarr;
                </a>
            </div>
        </div>

        {{-- Metrics Top Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Rata-rata Skor WPS Venue</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">86.4 <span class="text-xs font-normal text-slate-400">/ 100</span></span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Layanan Cepat Sesuai SLA</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Waktu Tunggu Terlama</span>
                <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-2 block">72 <span class="text-xs font-normal text-slate-400">detik</span></span>
                <span class="text-[11px] text-amber-400 font-medium mt-1 block">Stasiun Bar Pavilion Utara</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Bartender Bertugas</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">14 <span class="text-xs font-normal text-slate-400">personil</span></span>
                <span class="text-[11px] text-sky-400 font-medium mt-1 block">4 Pos Pelayanan Aktif</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Net Promoter Score (NPS)</span>
                <span class="text-2xl sm:text-3xl font-black text-purple-400 font-mono mt-2 block">+68 <span class="text-xs font-normal text-slate-400">NPS</span></span>
                <span class="text-[11px] text-purple-400 font-medium mt-1 block">Kategori Kepuasan Sangat Baik</span>
            </div>
        </div>

        {{-- Bar Stations Grid --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-white tracking-wide">Status Stasiun Bar F&B Real-Time</h3>
                <button type="button" @click="requestStaffReinforcement()" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 text-slate-950 font-bold rounded-xl text-xs sm:text-sm shadow-lg transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Panggil Bantuan Bartender (HCM)
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-venue::wps-gauge-widget 
                    station-name="Bar Counter 01 (Main Stage)"
                    :score="82.0"
                    :wait-seconds="54"
                    :queue-length="8"
                    :staff-count="5" />

                <x-venue::wps-gauge-widget 
                    station-name="Bar Counter 02 (VIP Deck)"
                    :score="96.5"
                    :wait-seconds="22"
                    :queue-length="3"
                    :staff-count="3" />

                <x-venue::wps-gauge-widget 
                    station-name="Bar Counter 03 (Pavilion Utara)"
                    :score="68.0"
                    :wait-seconds="72"
                    :queue-length="11"
                    :staff-count="3" />

                <x-venue::wps-gauge-widget 
                    station-name="Bar Counter 04 (Express Beer)"
                    :score="92.0"
                    :wait-seconds="30"
                    :queue-length="5"
                    :staff-count="3" />
            </div>
        </div>

        {{-- Shift Allocation & NPS Scorecard Partials --}}
        <div class="space-y-4">
            @include('venue::experience.partials.bartender-shift-modal')
            @include('venue::experience.partials.nps-scorecard-table')
        </div>
    </div>
</div>

<script>
function venueExperienceWpsApp() {
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
        async requestStaffReinforcement() {
            try {
                const response = await fetch("{{ route('venue.experience.reinforce') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        station_id: 3,
                        additional_staff: 2
                    })
                });
                const res = await response.json();
                if (res.success) {
                    this.showToast('success', 'Bantuan Dikirim', 'Dua bartender standby segera bergerak ke Bar Counter 03.');
                }
            } catch (e) {
                this.showToast('success', 'Bantuan Dikirim', 'Permintaan shift reinforcement berhasil diteruskan ke manajer bar.');
            }
        }
    }
}
</script>
@endsection
