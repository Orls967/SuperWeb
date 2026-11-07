@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="vaultAuditApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                        Audit Kriptografi & Vault Biometrik
                    </span>
                    <span class="text-xs text-slate-400 font-mono">ISO/IEC 27001 & UU PDP No. 27/2022</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Audit Integritas Vault & Hak Hapus Mandiri</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Verifikasi kepatuhan penyimpanan vektor terenkripsi, sertifikat pemusnahan data subjek (GDPR Article 17), dan integritas zeroization.
                </p>
            </div>
            <div>
                <a href="{{ route('venue.biometrics.kiosk') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Simulator Kios
                </a>
            </div>
        </div>

        {{-- KPI Audit Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Total Kredensial Terdaftar</span>
                <span class="text-2xl sm:text-3xl font-black text-white font-mono mt-2 block">1.240</span>
                <span class="text-[11px] text-sky-400 font-medium mt-1 block">Semua Format Hash 64-Hex</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Persetujuan UU PDP Sah</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">100%</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Versi UU-PDP-2022-V2</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Data Dicabut / Dimusnahkan</span>
                <span class="text-2xl sm:text-3xl font-black text-purple-400 font-mono mt-2 block">48</span>
                <span class="text-[11px] text-purple-400 font-medium mt-1 block">Sertifikat Zeroization Terbit</span>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 sm:p-5">
                <span class="text-xs text-slate-400 block">Anomali Citra Mentah</span>
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-2 block">0</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 block">Nol Gambar Plaintext (Aman)</span>
            </div>
        </div>

        {{-- Verification Certificate Generator --}}
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-xl backdrop-blur-md space-y-4">
            <h3 class="text-base font-bold text-white border-b border-slate-700 pb-3 flex items-center justify-between">
                <span>Penerbitan Sertifikat Verifikasi Pemusnahan Data Digital</span>
                <span class="text-xs font-mono text-emerald-400">HMAC-SHA256 Digital Seal</span>
            </h3>

            <p class="text-xs text-slate-300">
                Gunakan alat ini untuk memverifikasi segel kriptografi bahwa seluruh template biometrik tamu telah di-zeroize dan dimusnahkan secara permanen dari server aktif dan replika cold-storage.
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-3">
                <input type="text" placeholder="Masukkan ID Pengguna atau Kode Sertifikat (contoh: PDP-CERT-101-7A8B)" class="w-full sm:w-96 px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                <button type="button" class="w-full sm:w-auto px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-xs transition-colors shadow-lg shadow-cyan-950/40">
                    Verifikasi Segel Kripto &rarr;
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function vaultAuditApp() {
    return {
        auditStatus: 'PASSED'
    }
}
</script>
@endsection
