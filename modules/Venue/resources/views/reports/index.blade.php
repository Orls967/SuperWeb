<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan Tiket & Audit — Venue Reports</title>
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
            <span class="text-xs bg-purple-950 text-purple-400 border border-purple-800 px-2 py-0.5 rounded font-mono font-semibold">REPORTS & AUDIT</span>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <a href="/venue/events" class="text-slate-400 hover:text-white transition-colors">Acara & Tiket</a>
            <a href="/venue/gate" class="text-slate-400 hover:text-white transition-colors">Layar Gerbang 375px</a>
            <a href="/venue/zones" class="text-slate-400 hover:text-white transition-colors">Peta Kerumunan</a>
            <a href="/venue/tables" class="text-slate-400 hover:text-white transition-colors">Meja VIP</a>
            <a href="/venue/permits" class="text-slate-400 hover:text-white transition-colors">Izin Keramaian</a>
            <a href="/venue/reports" class="text-emerald-400 font-bold border-b-2 border-emerald-400 pb-1">Laporan & Audit</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-100 tracking-tight">Laporan Keuangan Penjualan Tiket & Audit Gerbang</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Rekonsiliasi per tier, per jam, pemindaian turnstile, serta ekspor berkas data CSV untuk audit fiskal dan regulasi hiburan.
                </p>
            </div>
            <a href="/venue/reports/export" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-950 transition-all flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Ekspor Rekap CSV</span>
            </a>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Tiket Diterbitkan</span>
                <span class="text-2xl font-black text-slate-100 tabular-nums mt-1 block">
                    {{ number_format($totalIssued, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 inline-block">Gapless sequential IDs</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Pindaian Diizinkan</span>
                <span class="text-2xl font-black text-emerald-400 tabular-nums mt-1 block">
                    {{ number_format($totalScanned, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Anti-passback validated</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Pindaian Ditolak (Anomali)</span>
                <span class="text-2xl font-black text-rose-400 tabular-nums mt-1 block">
                    {{ number_format($totalDenied, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-rose-400 mt-1 inline-block">Replay / underage / overcap</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Bruto Penjualan Tiket</span>
                <span class="text-2xl font-black text-purple-400 tabular-nums mt-1 block">
                    Rp {{ number_format($grossTicketRevenue, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-purple-400 mt-1 inline-block">Unearned / Revenue recognized</span>
            </div>
        </div>

        <!-- Tabel Rekap Per Tier -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Kinerja Penjualan Tiket Berdasarkan Tier</h2>
                <span class="text-xs text-slate-500 font-mono">D89-7 • Breakdown Kecepatan Penjualan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Tier & Acara</th>
                            <th class="px-6 py-3.5 text-right">Harga Dasar</th>
                            <th class="px-6 py-3.5 text-center">Kapasitas Alokasi</th>
                            <th class="px-6 py-3.5 text-center">Terjual</th>
                            <th class="px-6 py-3.5 text-right">Total Pendapatan (IDR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($tierSales as $item)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-100">{{ $item['tier_name'] }}</div>
                                    <div class="text-xs text-slate-400">{{ $item['event_title'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs tabular-nums text-slate-300">
                                    Rp {{ number_format($item['base_price'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums text-slate-300">
                                    {{ number_format($item['allocated_capacity'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums font-bold text-emerald-400">
                                    {{ number_format($item['tickets_sold'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-sm tabular-nums font-bold text-white">
                                    Rp {{ number_format($item['total_revenue_idr'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-xs text-slate-500 italic">
                                    Belum ada data penjualan per tier tiket.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>

