@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="biometricKioskApp()">
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
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '❌'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                        Face-ID Door Entry Hub
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 117.1 • Anti-Spoof Liveness AI</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Simulator Kios Turnstile Biometrik</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Verifikasi identitas wajah nirsentuh tanpa tiket fisik, deteksi serangan topeng/layar 2D, dan enkripsi hash searah standar privasi UU No. 27/2022.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.biometrics.enrolment') }}" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-xl text-xs sm:text-sm font-bold shadow-lg shadow-cyan-900/40 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Pendaftaran Anggota (Enrolment) &rarr;
                </a>
            </div>
        </div>

        {{-- Mobile Viewport Wrapper (375px responsive simulator) --}}
        <div class="flex justify-center">
            <div class="w-full max-w-[420px] bg-slate-950 rounded-3xl border-4 border-slate-800 p-5 shadow-2xl relative overflow-hidden">
                {{-- Kiosk Screen Header --}}
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-mono font-bold text-white uppercase">Turnstile Kiosk 01</span>
                    </div>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-400">IR + RGB 60FPS</span>
                </div>

                {{-- Camera Reticle Viewfinder --}}
                <div class="relative aspect-square w-full bg-slate-900 rounded-2xl border-2 overflow-hidden flex items-center justify-center transition-colors duration-300"
                     :class="scanStatus === 'SUCCESS' ? 'border-emerald-500 shadow-lg shadow-emerald-500/20' : (scanStatus === 'FAILED' ? 'border-rose-500 shadow-lg shadow-rose-500/20' : 'border-slate-700')">
                    
                    {{-- Reticle Grid Lines --}}
                    <div class="absolute inset-0 pointer-events-none opacity-20 bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:16px_16px]"></div>

                    {{-- Face Oval Framing Guide --}}
                    <div class="w-48 h-60 rounded-[45%] border-2 border-dashed transition-all duration-300 relative flex items-center justify-center"
                         :class="isScanning ? 'border-cyan-400 animate-pulse scale-105' : (scanStatus === 'SUCCESS' ? 'border-emerald-400 scale-100' : 'border-slate-500')">
                        
                        {{-- Scanning Laser Line --}}
                        <div x-show="isScanning" 
                             class="absolute w-full h-1 bg-gradient-to-r from-transparent via-cyan-400 to-transparent animate-bounce"></div>

                        {{-- Icon Placeholder or Face Illustration --}}
                        <svg class="w-24 h-24 transition-colors duration-300" 
                             :class="scanStatus === 'SUCCESS' ? 'text-emerald-400' : (scanStatus === 'FAILED' ? 'text-rose-400' : 'text-slate-600')"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    {{-- Dynamic Status Pill --}}
                    <div class="absolute bottom-4 inset-x-4 flex justify-center">
                        <span class="px-3 py-1.5 rounded-full text-xs font-mono font-bold uppercase tracking-wider backdrop-blur-md shadow-lg"
                              :class="scanStatus === 'SUCCESS' ? 'bg-emerald-500 text-slate-950' : (scanStatus === 'FAILED' ? 'bg-rose-500 text-white' : 'bg-slate-800/90 text-slate-300 border border-slate-700')">
                            <span x-text="scanMessage"></span>
                        </span>
                    </div>
                </div>

                {{-- Liveness & Hardware Check Meters --}}
                <div class="space-y-3 mt-4 text-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Skor Keaslian Biometrik (Liveness):</span>
                        <strong class="font-mono text-white" x-text="(livenessScore * 100).toFixed(0) + '%'"></strong>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500" 
                             :class="livenessScore >= 0.85 ? 'bg-emerald-400' : 'bg-amber-400'"
                             :style="'width: ' + (livenessScore * 100) + '%'"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 text-[11px] font-mono">
                        <div class="bg-slate-900 border border-slate-800 rounded-lg p-2 flex items-center justify-between">
                            <span class="text-slate-400">Mikro-Gerak:</span>
                            <span class="text-emerald-400 font-bold">TERDETEKSI</span>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 rounded-lg p-2 flex items-center justify-between">
                            <span class="text-slate-400">Anti-Moire (Layar):</span>
                            <span class="text-emerald-400 font-bold">LOLOS (0.02)</span>
                        </div>
                    </div>
                </div>

                {{-- Interactive Controls for Simulation --}}
                <div class="space-y-2 mt-5 pt-4 border-t border-slate-800">
                    <button type="button" 
                            @click="simulateScan(true)" 
                            :disabled="isScanning"
                            class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-950/40 transition-all flex items-center justify-center gap-2">
                        <span>Pindai Wajah Asli (Tamu Terdaftar)</span>
                    </button>

                    <button type="button" 
                            @click="simulateScan(false)" 
                            :disabled="isScanning"
                            class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-rose-300 border border-rose-500/30 rounded-xl text-xs font-semibold transition-all">
                        Simulasikan Serangan Foto Palsu (Spoof Test)
                    </button>
                </div>
            </div>
        </div>

        {{-- Liveness Presentation Attack Detection Audit Log --}}
        @include('venue::biometrics.partials.liveness-audit-log')
    </div>
</div>

<script>
function biometricKioskApp() {
    return {
        isScanning: false,
        scanStatus: 'IDLE',
        scanMessage: 'Arahkan Wajah ke Kamera',
        livenessScore: 0.94,
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
        async simulateScan(isGenuine) {
            this.isScanning = true;
            this.scanStatus = 'IDLE';
            this.scanMessage = 'Memverifikasi Liveness & Vektor...';
            
            setTimeout(async () => {
                this.isScanning = false;
                if (isGenuine) {
                    this.scanStatus = 'SUCCESS';
                    this.scanMessage = 'AKSES DIIZINKAN (SILAKAN MASUK)';
                    this.livenessScore = 0.98;
                    this.showToast('success', 'Face-ID Valid', 'Gerbang Turnstile Alpha 01 terbuka 5 detik.');
                } else {
                    this.scanStatus = 'FAILED';
                    this.scanMessage = 'AKSES DITOLAK (SPOOF / TIDAK COCOK)';
                    this.livenessScore = 0.32;
                    this.showToast('danger', 'Verifikasi Gagal', 'Liveness gagal atau foto layar terdeteksi. Hubungi petugas.');
                }
            }, 1200);
        }
    }
}
</script>
@endsection
