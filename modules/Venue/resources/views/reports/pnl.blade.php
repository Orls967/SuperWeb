<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Laba/Rugi Acara (Event P&L) — Venue Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <a href="/venue/reports" class="text-emerald-400 font-black text-xl tracking-wider hover:text-emerald-300">← SUPERWEB // VENUE</a>
            <span class="text-xs bg-purple-950 text-purple-400 border border-purple-800 px-2 py-0.5 rounded font-mono font-semibold">EVENT P&L</span>
        </div>
        <div>
            <a href="/venue/reports" class="text-xs text-slate-400 hover:text-white">Laporan Utama</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight">Analisis Laba Rugi Komprehensif Acara (Event P&L)</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Pendapatan tiket diakui, reservasi botol VIP, konsumsi bar, potongan pajak hiburan daerah (PBJT 10%), serta biaya premi asuransi (G7-K15).
                </p>
            </div>
        </div>

        <!-- P&L Table Breakdown -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Rincian Pos Akuntansi Laba Rugi</h2>
                <span class="text-xs font-mono text-slate-500">Standar Ledger G7 • Akrual ke Revenue</span>
            </div>

            <div class="p-6 space-y-4">
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400">1. Pendapatan Usaha (Revenue Inflow)</h3>
                    <div class="divide-y divide-slate-800 bg-slate-950/60 rounded-xl border border-slate-800 text-sm">
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Penjualan Tiket Masuk (Recognized Ticket Revenue)</span>
                            <span class="font-mono font-bold text-slate-100 tabular-nums">Rp 420.000.000</span>
                        </div>
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Pendapatan Meja VIP & Bottle Service Minimum Spend</span>
                            <span class="font-mono font-bold text-slate-100 tabular-nums">Rp 125.000.000</span>
                        </div>
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Pendapatan F&B Bar & Minuman Keras (POS Spirits)</span>
                            <span class="font-mono font-bold text-slate-100 tabular-nums">Rp 85.000.000</span>
                        </div>
                        <div class="px-4 py-3 bg-slate-900/60 flex items-center justify-between font-bold">
                            <span class="text-emerald-400">Total Pendapatan Bruto (Gross Revenue)</span>
                            <span class="font-mono text-base text-emerald-400 tabular-nums">Rp 630.000.000</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-400">2. Pajak & Beban Langsung (Deductions & Expenses)</h3>
                    <div class="divide-y divide-slate-800 bg-slate-950/60 rounded-xl border border-slate-800 text-sm">
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Pajak Hiburan Daerah (PBJT 10% Terutang)</span>
                            <span class="font-mono font-bold text-rose-400 tabular-nums">- Rp 63.000.000</span>
                        </div>
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Beban Premi Asuransi Keramaian Acara (G7-K15 Cover Binder)</span>
                            <span class="font-mono font-bold text-rose-400 tabular-nums">- Rp 15.000.000</span>
                        </div>
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-slate-300">Beban Operasional Petugas Gerbang & Keamanan Medis</span>
                            <span class="font-mono font-bold text-rose-400 tabular-nums">- Rp 22.500.000</span>
                        </div>
                        <div class="px-4 py-3 bg-slate-900/60 flex items-center justify-between font-bold">
                            <span class="text-rose-400">Total Beban Langsung & Pajak</span>
                            <span class="font-mono text-base text-rose-400 tabular-nums">- Rp 100.500.000</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800 flex items-center justify-between p-4 bg-emerald-950/40 border border-emerald-500/50 rounded-xl">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-emerald-400 font-bold block">Margin Operasional Bersih Acara</span>
                        <span class="text-2xl font-black text-white tabular-nums tracking-tight">Rp 529.500.000</span>
                    </div>
                    <div class="text-right font-mono">
                        <span class="text-xs text-slate-400 block">Margin Rasio:</span>
                        <span class="text-xl font-bold text-emerald-400">84.05%</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
