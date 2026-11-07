<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reklamasi & Lingkungan Tambang</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Reklamasi & Pemantauan Lingkungan Tambang</h1>
                <p class="text-sm text-slate-500">Jaminan reklamasi, penutupan tambang pasca-operasi, dan sensor lingkungan real-time.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                    Kepatuhan AMDAL Terjaga
                </span>
            </div>
        </header>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Jadwal & Provisi Dana Jaminan Reklamasi</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600">
                            <th class="p-3">Area / Zona</th>
                            <th class="p-3">Target Hektar</th>
                            <th class="p-3">Tahun/Kuartal</th>
                            <th class="p-3">Biaya Estimasi (IDR)</th>
                            <th class="p-3">Jaminan Bank</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $s)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-medium">{{ $s->area_name }}</td>
                                <td class="p-3">{{ number_format($s->target_hectares, 1) }} Ha</td>
                                <td class="p-3">{{ $s->planned_year }} Q{{ $s->planned_quarter }}</td>
                                <td class="p-3 font-mono">Rp {{ number_format($s->estimated_cost_idr) }}</td>
                                <td class="p-3">{{ $s->escrow_bank ?? 'Mandiri Escrow' }}</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-800">
                                        {{ $s->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-slate-400">Belum ada jadwal reklamasi yang dijadwalkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>

