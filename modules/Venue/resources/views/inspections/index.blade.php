<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan Tas & Kepatuhan Keamanan — Venue Security</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <span class="text-emerald-400 font-black text-xl tracking-wider">SUPERWEB // VENUE</span>
            <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono font-semibold">SECURITY AUDIT</span>
        </div>
        <div class="flex items-center space-x-4 text-xs">
            <a href="/venue/gate" class="text-slate-400 hover:text-white">Gerbang Pindai</a>
            <a href="/venue/zones" class="text-slate-400 hover:text-white">Peta Kerumunan</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-6 space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight">Audit Pemeriksaan Acak & Penyitaan Barang Larangan (D89-5)</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Pencatatan pemeriksaan fisik tas di gerbang masuk sesuai UU PDP (zero data medis/pribadi mentah), penyitaan suar/laser/senjata, dan eskalasi ke kepolisian.
                </p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Pemeriksaan Selesai</span>
                <span class="text-2xl font-black text-emerald-400 tabular-nums mt-1 block">1.420 Tamu</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">100% Bersih & Sesuai Prosedur</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Barang Disita (Contraband)</span>
                <span class="text-2xl font-black text-amber-400 tabular-nums mt-1 block">18 Kasus</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Suar, laser pointer, botol kaca</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Eskalasi Polisi</span>
                <span class="text-2xl font-black text-rose-400 tabular-nums mt-1 block">0 Kasus</span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Tidak ada temuan zat berbahaya</span>
            </div>
        </div>

        <!-- Tabel Riwayat Inspeksi -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Log Inspeksi Keamanan Pintu Masuk</h2>
                <span class="text-xs font-mono text-slate-500">Standar PDP Terakreditasi</span>
            </div>

            <div class="p-6">
                <div class="space-y-3">
                    <div class="p-3.5 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs font-mono">
                        <span class="text-slate-400">INSP-7A2B9F01</span>
                        <span class="text-slate-300">GATE-MAIN-01</span>
                        <span class="text-slate-400">Petugas: SEC-BADGE-44</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">CLEARED</span>
                    </div>
                    <div class="p-3.5 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs font-mono">
                        <span class="text-slate-400">INSP-8C3D1E02</span>
                        <span class="text-slate-300">GATE-VIP-02</span>
                        <span class="text-slate-400">Petugas: SEC-BADGE-12</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">CONFISCATED (LASER)</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
