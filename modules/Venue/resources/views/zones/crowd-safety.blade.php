<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsol Komando Crowd Safety — Venue Emergency Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <a href="/venue/zones" class="text-emerald-400 font-black text-xl tracking-wider hover:text-emerald-300">← SUPERWEB // VENUE</a>
            <span class="text-xs bg-rose-950 text-rose-400 border border-rose-800 px-2 py-0.5 rounded font-mono font-semibold">CROWD CRUSH PROTOCOL</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-xs font-mono text-slate-400 tabular-nums">SISTEM SIAGA 24/7</span>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Emergency Banner Header -->
        <div class="bg-gradient-to-r from-slate-900 via-rose-950/40 to-slate-900 border border-rose-600/40 p-6 rounded-2xl shadow-2xl flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center space-x-3">
                    <span class="inline-block w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-rose-950 text-rose-400 border border-rose-800">
                        MONITORING ANTREAN & KEPADATAN LIVE
                    </span>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">Pusat Tanggap Darurat Kerumunan & Jalur Evakuasi</h1>
                <p class="text-sm text-slate-400">
                    Protokol otomatis penanganan lonjakan massa (D89-3): aktivasi kunci gerbang sekunder, pengalihan rute evakuasi, dan koordinasi tim medis ambulans.
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <span class="px-4 py-2 bg-slate-950 border border-slate-800 text-xs font-mono text-slate-300 rounded-xl">
                    Ambulans: <strong class="text-emerald-400">SIAGA 1</strong>
                </span>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Zona Dalam Pengawasan</span>
                <span class="text-2xl font-black text-slate-100 tabular-nums mt-1 block">6 Zona</span>
                <span class="text-[11px] text-emerald-400 mt-1 inline-block">Sensor optik turnstile aktif</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Insiden Tercatat</span>
                <span class="text-2xl font-black text-amber-400 tabular-nums mt-1 block">0 Aktif</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Semua batas di bawah 85%</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Pintu Darurat Terbuka</span>
                <span class="text-2xl font-black text-cyan-400 tabular-nums mt-1 block">4 Koridor</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Bebas halangan & rintangan</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Waktu Respon Evakuasi</span>
                <span class="text-2xl font-black text-emerald-400 tabular-nums mt-1 block">&lt; 90 Detik</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Standar keselamatan keramaian</span>
            </div>
        </div>

        <!-- Panel Intervensi Cepat -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h2 class="text-base font-bold text-slate-200">Panduan Tindakan Operasional Petugas Crowd Safety</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 space-y-2">
                    <span class="font-bold text-emerald-400 block uppercase">1. Fase Normal (&lt; 70%)</span>
                    <p class="text-slate-400 leading-relaxed">
                        Laju pindaian gerbang normal. Turnstile beroperasi penuh. Pertahankan sirkulasi reguler di koridor utama.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 space-y-2">
                    <span class="font-bold text-amber-400 block uppercase">2. Fase Waspada (70% - 84%)</span>
                    <p class="text-slate-400 leading-relaxed">
                        Perlambat laju pindaian masuk gerbang. Posisikan staf keamanan tambahan di titik botol leher (bottleneck).
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 space-y-2">
                    <span class="font-bold text-rose-400 block uppercase">3. Fase Darurat (≥ 85%)</span>
                    <p class="text-slate-400 leading-relaxed">
                        Hentikan pemindaian masuk (IN). Buka seluruh gerbang sekunder dan pintu darurat keluar. Hubungi tim medis.
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
