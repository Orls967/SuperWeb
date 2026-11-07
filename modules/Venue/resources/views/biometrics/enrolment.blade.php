@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="biometricEnrolmentApp()">
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
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : 'ℹ️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        Portal Privasi & Pendaftaran Biometrik
                    </span>
                    <span class="text-xs text-slate-400 font-mono">UU PDP No. 27/2022 Compliant</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Pendaftaran Akses Gerbang Mandiri</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Daftarkan verifikasi wajah untuk melewati turnstile tanpa antrean tiket. Foto asli tidak pernah disimpan di server kami.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.biometrics.kiosk') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Simulator Kios
                </a>
            </div>
        </div>

        {{-- Privacy Vault Transparency Card --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl backdrop-blur-md space-y-4">
            <div class="flex items-start gap-3">
                <span class="p-2.5 rounded-xl bg-cyan-500/20 text-cyan-400 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-white">Prinsip Perlindungan Data Pribadi & Kriptografi Searah</h3>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Sistem kami menggunakan algoritma <strong>BiometricLivenessMatcherEngine (V13)</strong>. Citra wajah Anda diubah menjadi vektor matematis bergaram (salted embedding) 512-dimensi yang langsung di-hash menggunakan HMAC-SHA256. Tidak ada rekonstruksi gambar mentah yang dapat dilakukan dari database.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 text-xs font-mono">
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
                    <span class="text-slate-400 block text-[11px]">Format Tersimpan:</span>
                    <strong class="text-emerald-400">SHA-256 Digest (64 Hex)</strong>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
                    <span class="text-slate-400 block text-[11px]">Hak Subjek Data:</span>
                    <strong class="text-sky-400">Hak Hapus Mandiri (Erasure)</strong>
                </div>
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
                    <span class="text-slate-400 block text-[11px]">Masa Retensi:</span>
                    <strong class="text-amber-400">Otomatis Terhapus Pasca Acara</strong>
                </div>
            </div>
        </div>

        {{-- Enrolment Form --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl backdrop-blur-md space-y-5">
            <h3 class="text-base font-bold text-white border-b border-slate-700 pb-3">Formulir Persetujuan & Pendaftaran</h3>

            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="consentAccepted" class="mt-1 w-4 h-4 rounded text-emerald-500 bg-slate-900 border-slate-700 focus:ring-0">
                    <span class="text-xs text-slate-300 leading-relaxed">
                        Saya secara sadar memberikan persetujuan eksplisit sesuai Pasal 20 UU No. 27/2022 tentang Pelindungan Data Pribadi untuk memproses fitur biometrik wajah saya demi verifikasi akses pintu otomatis acara festival ini.
                    </span>
                </label>
            </div>

            <div class="p-4 bg-slate-900/90 rounded-xl border border-slate-800 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Status Kredensial Saat Ini:</span>
                    <span class="font-mono font-bold px-2.5 py-0.5 rounded-full text-[11px]"
                          :class="isEnrolled ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-400'">
                        <span x-text="isEnrolled ? 'TERDAFTAR & AKTIF' : 'BELUM TERDAFTAR'"></span>
                    </span>
                </div>
                <div x-show="isEnrolled" class="text-xs font-mono text-slate-400 break-all">
                    Fingerprint Template: <span class="text-sky-400">7a8f9c1b4e2d8803e1a0...3f9e</span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                <button type="button" 
                        @click="executeEnrolment()" 
                        :disabled="!consentAccepted || isEnrolled"
                        :class="consentAccepted && !isEnrolled ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 text-white' : 'bg-slate-800 text-slate-500 cursor-not-allowed'"
                        class="w-full sm:w-auto px-6 py-3 rounded-xl font-bold text-xs sm:text-sm shadow-lg transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Daftarkan Biometrik Wajah
                </button>

                <button type="button" 
                        x-show="isEnrolled"
                        @click="executeRevocation()" 
                        class="w-full sm:w-auto px-5 py-3 bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-500/40 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Cabut Persetujuan & Musnahkan Data
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function biometricEnrolmentApp() {
    return {
        consentAccepted: false,
        isEnrolled: false,
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
        async executeEnrolment() {
            this.isEnrolled = true;
            this.showToast('success', 'Biometrik Berhasil Didaftarkan', 'Kredensial Face-ID Anda siap digunakan di seluruh turnstile festival.');
        },
        async executeRevocation() {
            this.isEnrolled = false;
            this.consentAccepted = false;
            this.showToast('success', 'Data Dimusnahkan', 'Template hash telah dihapus permanen sesuai UU No. 27/2022 Pasal 20.');
        }
    }
}
</script>
@endsection
