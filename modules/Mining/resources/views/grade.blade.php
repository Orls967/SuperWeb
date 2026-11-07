<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Control & Neraca Metalurgi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Grade Control & Rekonsiliasi Neraca Metalurgi M5</h1>
                <p class="text-sm text-slate-500">Rekonsiliasi tiga titik (Feed, Konsentrat, Tailing) dan audit perbandingan assay lab vs umpire.</p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Sistem M5 Aktif
                </span>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-xs font-medium text-slate-500 uppercase">Total Stockpile Aktif</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ count($stockpiles) }}</div>
                <div class="text-xs text-slate-500 mt-2">Kapasitas tercatat di konsesi</div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-xs font-medium text-slate-500 uppercase">Rekonsiliasi Bulan Berjalan</div>
                <div class="text-2xl font-bold text-blue-600 mt-1">{{ count($runs) }}</div>
                <div class="text-xs text-slate-500 mt-2">Putaran neraca massa tuntas</div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-xs font-medium text-slate-500 uppercase">Umpire Assay Dipantau</div>
                <div class="text-2xl font-bold text-indigo-600 mt-1">{{ count($assays) }}</div>
                <div class="text-xs text-slate-500 mt-2">Deteksi bias lab terakreditasi KAN</div>
            </div>
        </div>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Riwayat Rekonsiliasi Neraca Massa</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600">
                            <th class="p-3">Nomor Putaran</th>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Feed (Ton)</th>
                            <th class="p-3">Konsentrat (Ton)</th>
                            <th class="p-3">Tailing (Ton)</th>
                            <th class="p-3">Diskrepansi</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($runs as $r)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-mono font-medium text-blue-600">{{ $r->run_number }}</td>
                                <td class="p-3">{{ $r->period_month }}</td>
                                <td class="p-3">{{ number_format($r->total_ore_mined_tons) }}</td>
                                <td class="p-3">{{ number_format($r->total_stockpile_tons) }}</td>
                                <td class="p-3">{{ number_format($r->total_shipped_tons) }}</td>
                                <td class="p-3">{{ $r->variance_tons }} Ton ({{ $r->variance_bps }} bps)</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $r->status->value ?? $r->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-slate-400">Belum ada data rekonsiliasi tersimpan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
