<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kepatuhan Royalti & PNBP Minerba</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manajemen Royalti & PNBP Minerba (M4)</h1>
                <p class="text-sm text-slate-500">Kalkulasi royalti progresif berversi PP 19/2025, integrasi HBA/HMA, dan pembukuan buku besar.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Regulasi PP 19/2025
                </span>
            </div>
        </header>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">SPT Royalti Minerba Bulanan</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600">
                            <th class="p-3">Nomor SPT</th>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Volume (Ton)</th>
                            <th class="p-3">Tarif</th>
                            <th class="p-3">Royalti (USD)</th>
                            <th class="p-3">Royalti (IDR)</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $ret)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-mono font-medium text-blue-600">{{ $ret->return_number }}</td>
                                <td class="p-3">{{ $ret->period_month }}</td>
                                <td class="p-3">{{ number_format($ret->verified_volume_tons) }}</td>
                                <td class="p-3">{{ ($ret->rate_bps ?? 0) / 100 }}%</td>
                                <td class="p-3 font-mono">${{ number_format(($ret->calculated_royalty_usd_cents ?? 0) / 100, 2) }}</td>
                                <td class="p-3 font-mono">Rp {{ number_format($ret->calculated_royalty_idr ?? 0) }}</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                        {{ $ret->status->value ?? $ret->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-slate-400">Belum ada SPT royalti tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
