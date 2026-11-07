@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="crowdSafetyDashboardApp()">
    {{-- Toast Notification --}}
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
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '🚨'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Breadcrumb & Emergency Evacuation Trigger Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-rose-500/20 text-rose-400 border border-rose-500/30">
                        Pusat Komando Keselamatan Kerumunan
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        AI Safety Predictive: V14 Certified
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20">
                        NFPA 101 Egress Protocol
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white mt-2">
                    Crowd Safety & Biometric Command Hub
                </h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-3xl">
                    Pemantauan kapasitas riil, densitas per meter persegi, aliran optik evakuasi, dan pelepasan gerbang turnstile otomatis untuk mitigasi ancaman desakan massa (Fase 117.2 & 117.3).
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('venue.biometrics.kiosk') }}" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-sky-400 border border-slate-700 font-semibold rounded-xl text-xs sm:text-sm transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    Kios Face-ID
                </a>
                <button type="button" 
                        @click="openEmergencyModal = true" 
                        class="px-4 py-2.5 bg-gradient-to-r from-rose-600 to-red-700 hover:from-rose-500 hover:to-red-600 text-white font-bold rounded-xl shadow-lg shadow-rose-900/50 text-xs sm:text-sm transition-all flex items-center gap-2 animate-pulse">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    DARURAT: Buka Seluruh Gerbang
                </button>
            </div>
        </div>

        {{-- Top KPI Cards Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-4 sm:p-5 backdrop-blur-md">
                <span class="text-xs text-slate-400 font-medium block">Total Okupansi Venue</span>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-2xl sm:text-3xl font-black text-white font-mono">1.850</span>
                    <span class="text-xs text-slate-400 font-mono">/ 2.500 Kapasitas</span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-1.5 mt-3 overflow-hidden">
                    <div class="bg-emerald-400 h-1.5 rounded-full" style="width: 74%"></div>
                </div>
                <span class="text-[11px] text-emerald-400 mt-2 block font-medium">74% Kapasitas Terisi (Aman)</span>
            </div>

            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-4 sm:p-5 backdrop-blur-md">
                <span class="text-xs text-slate-400 font-medium block">Densitas Tertinggi</span>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-2xl sm:text-3xl font-black text-rose-400 font-mono">4.2</span>
                    <span class="text-xs text-slate-400 font-mono">orang / m²</span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-1.5 mt-3 overflow-hidden">
                    <div class="bg-rose-500 h-1.5 rounded-full" style="width: 84%"></div>
                </div>
                <span class="text-[11px] text-rose-400 mt-2 block font-medium">Zona Main Stage (Surge Alert)</span>
            </div>

            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-4 sm:p-5 backdrop-blur-md">
                <span class="text-xs text-slate-400 font-medium block">Tingkat Kebisingan dB SPL</span>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-2xl sm:text-3xl font-black text-amber-400 font-mono">98.4</span>
                    <span class="text-xs text-slate-400 font-mono">dBA Peak</span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-1.5 mt-3 overflow-hidden">
                    <div class="bg-amber-400 h-1.5 rounded-full" style="width: 68%"></div>
                </div>
                <span class="text-[11px] text-amber-400 mt-2 block font-medium">Stage Monitors: Batas Aman NIOSH</span>
            </div>

            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-4 sm:p-5 backdrop-blur-md">
                <span class="text-xs text-slate-400 font-medium block">Throughput Gerbang Masuk</span>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-2xl sm:text-3xl font-black text-sky-400 font-mono">48</span>
                    <span class="text-xs text-slate-400 font-mono">tamu / menit</span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-1.5 mt-3 overflow-hidden">
                    <div class="bg-sky-400 h-1.5 rounded-full" style="width: 80%"></div>
                </div>
                <span class="text-[11px] text-sky-400 mt-2 block font-medium">8 Turnstiles Face-ID Berjalan</span>
            </div>
        </div>

        {{-- Interactive Heatmap Partial --}}
        @include('venue::crowd.partials.interactive-heatmap')

        {{-- Velocity Chart & Egress Guidance Partials --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('venue::crowd.partials.crowd-velocity-chart')
            @include('venue::crowd.partials.egress-route-guidance')
        </div>

        {{-- Turnstiles Hardware Grid & Real-time Ticker --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-white tracking-wide flex items-center gap-2">
                    <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Array Gerbang Turnstile Fisik (IoT Controller)
                </h3>
                <span class="text-xs text-slate-400">Total 8 Gerbang Terhubung</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-venue::turnstile-gate-card 
                    gate-name="Turnstile Alpha 01 (VIP Ingress)"
                    status="NORMAL"
                    mode="FACE_ID_ONLY"
                    :scans-per-minute="18"
                    :total-scanned="340"
                    hardware-health="HEALTHY" />

                <x-venue::turnstile-gate-card 
                    gate-name="Turnstile Alpha 02 (General Ingress)"
                    status="NORMAL"
                    mode="BIOMETRIC_AND_BARCODE"
                    :scans-per-minute="28"
                    :total-scanned="512"
                    hardware-health="HEALTHY" />

                <x-venue::turnstile-gate-card 
                    gate-name="Turnstile Bravo 03 (General Ingress)"
                    status="NORMAL"
                    mode="BIOMETRIC_AND_BARCODE"
                    :scans-per-minute="26"
                    :total-scanned="498"
                    hardware-health="HEALTHY" />

                <x-venue::turnstile-gate-card 
                    gate-name="Turnstile Bravo 04 (Secondary Ingress)"
                    status="SLOW_ENTRY_RESTRICTED"
                    mode="BARCODE_ONLY"
                    :scans-per-minute="12"
                    :total-scanned="220"
                    hardware-health="HEALTHY" />
            </div>
        </div>

        {{-- Emergency 2-Step Confirmation Modal --}}
        <div x-show="openEmergencyModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             style="display: none;">
            <div class="bg-slate-900 border-2 border-rose-500 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5" @click.away="openEmergencyModal = false">
                <div class="flex items-center gap-3 text-rose-500">
                    <span class="p-3 rounded-xl bg-rose-500/20">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-xl font-black text-white">KONFIRMASI PROTOKOL DARURAT</h3>
                        <p class="text-xs text-rose-300 font-mono">SAGA: CrowdEmergencyEvacuationSaga</p>
                    </div>
                </div>

                <div class="bg-rose-950/40 border border-rose-800/60 rounded-xl p-4 text-xs text-rose-200 space-y-2">
                    <p class="font-bold">Aksi ini akan segera menjalankan perintah:</p>
                    <ul class="list-disc list-inside space-y-1 font-mono text-[11px]">
                        <li>Lockdown gerbang ingress utama (mencegah penumpukan tambahan).</li>
                        <li>Pelepasan fail-safe gerbang sekunder & exit darurat (rotasi bebas tanpa tiket).</li>
                        <li>Pemanggilan armada paramedis on-site via Hospital Medical Dispatcher.</li>
                        <li>Aktivasi sirine audio multibahasa evakuasi terkoordinasi.</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Ketik kalimat konfirmasi: <span class="font-mono text-rose-400">EVAKUASI SEKARANG</span></label>
                    <input type="text" x-model="emergencyConfirmInput" placeholder="EVAKUASI SEKARANG" class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-sm focus:border-rose-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openEmergencyModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-semibold transition-colors">
                        Batal
                    </button>
                    <button type="button" 
                            @click="triggerEmergencyEvacuation()" 
                            :disabled="emergencyConfirmInput !== 'EVAKUASI SEKARANG'"
                            :class="emergencyConfirmInput === 'EVAKUASI SEKARANG' ? 'bg-rose-600 hover:bg-rose-500 text-white' : 'bg-slate-800 text-slate-500 cursor-not-allowed'"
                            class="px-5 py-2.5 rounded-xl text-sm font-black tracking-wide shadow-lg transition-all">
                        EKSEKUSI DARURAT &rarr;
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function crowdSafetyDashboardApp() {
    return {
        openEmergencyModal: false,
        emergencyConfirmInput: '',
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
            setTimeout(() => { this.toast.visible = false; }, 5000);
        },
        async triggerEmergencyEvacuation() {
            try {
                const response = await fetch("{{ route('venue.crowd.emergency') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        zone_id: 1,
                        reason: 'OPERATOR_OVERRIDE_PANIC_EVACUATION'
                    })
                });
                
                const result = await response.json();
                this.openEmergencyModal = false;
                this.emergencyConfirmInput = '';
                
                if (result.success) {
                    this.showToast('danger', 'Protokol Evakuasi Aktif', 'Seluruh gerbang exit darurat telah dirilis ke mode rotasi bebas.');
                } else {
                    this.showToast('danger', 'Gagal Mengeksekusi', result.message || 'Kesalahan sistem.');
                }
            } catch (err) {
                this.openEmergencyModal = false;
                this.showToast('danger', 'Panggilan Gagal', err.message);
            }
        }
    }
}
</script>
@endsection
