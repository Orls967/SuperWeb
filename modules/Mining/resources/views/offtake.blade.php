<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan Komoditas & Saga Offtake</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manajemen Kontrak & Pengapalan Offtake</h1>
                <p class="text-sm text-slate-500">Saga siklus pengapalan: nominasi kapal, L/C TradeFinance, pemuatan, dan kompensasi regulasi.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                    Saga Terorkestrasi
                </span>
            </div>
        </header>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Pengapalan Offtake & Status Saga</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600">
                            <th class="p-3">Kode Pengapalan</th>
                            <th class="p-3">Nama Kapal</th>
                            <th class="p-3">Tujuan</th>
                            <th class="p-3">Volume Nominasi (Ton)</th>
                            <th class="p-3">Volume Dimuat (Ton)</th>
                            <th class="p-3">Ref L/C</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shipments as $shp)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-mono font-medium text-blue-600">{{ $shp->shipment_number }}</td>
                                <td class="p-3">{{ $shp->vessel_name }}</td>
                                <td class="p-3">{{ $shp->is_export ? 'EKSPOR' : 'DOMESTIK' }}</td>
                                <td class="p-3">{{ number_format($shp->nominated_volume_tons) }}</td>
                                <td class="p-3">{{ number_format($shp->loaded_volume_tons) }}</td>
                                <td class="p-3 font-mono text-xs">{{ $shp->lc_ref ?? '-' }}</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                        {{ $shp->status->value ?? $shp->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-slate-400">Belum ada aktivitas pengapalan offtake.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
