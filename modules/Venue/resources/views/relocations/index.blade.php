<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Relokasi Zona & Cuaca Buruk — Venue Operations</title>
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
            <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono font-semibold">WEATHER RELOCATION</span>
        </div>
        <div class="flex items-center space-x-4 text-xs">
            <a href="/venue/zones" class="text-slate-400 hover:text-white">Peta Okupansi</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-6 space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight">Pengalihan Zona Akibat Cuaca Ekstrem (D89-6)</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Respons cerdas penutupan zona pantai/terbuka, pemindahan kapasitas ke zona dalam ruang (indoor), serta pencairan pengembalian dana kompensasi pro-rata secara otomatis.
                </p>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h2 class="text-base font-bold text-slate-200">Riwayat Pengalihan & Kompensasi Patrons</h2>
            <div class="space-y-3">
                <div class="p-4 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-mono font-bold text-amber-400 block">RELOC-POOL-TO-INDOOR</span>
                        <span class="text-slate-400 mt-0.5 block">Dari: Outdoor Pool Deck → Ke: Main Indoor Hall</span>
                    </div>
                    <div class="text-right">
                        <span class="font-mono text-emerald-400 font-bold tabular-nums block">120 Tiket Dialihkan</span>
                        <span class="text-[11px] text-slate-500 block">Kompensasi 20%: Rp 8.400.000 dikembalikan</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
