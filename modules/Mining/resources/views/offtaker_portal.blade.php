<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Mitra Offtaker Tambang</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <header class="mb-6 border-b pb-4">
            <h1 class="text-2xl font-bold text-slate-800">Portal Mitra Offtaker Tambang</h1>
            <p class="text-sm text-slate-500">Alokasi kuota kontrak, nominasi tongkang/vessel, dan status kargo komoditas.</p>
        </header>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Daftar Kontrak Aktif Anda</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50 text-slate-600">
                            <th class="p-3">Kode Kontrak</th>
                            <th class="p-3">Komoditas</th>
                            <th class="p-3">Tujuan</th>
                            <th class="p-3">Volume Kontrak (kg)</th>
                            <th class="p-3">Harga Dasar (USD Cents)</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $c)
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-mono font-medium text-blue-600">{{ $c->contract_code ?? $c->contract_number }}</td>
                                <td class="p-3">{{ $c->commodity }}</td>
                                <td class="p-3">{{ $c->destination_type }}</td>
                                <td class="p-3">{{ number_format($c->contracted_tonnage_kg) }} kg</td>
                                <td class="p-3">${{ number_format($c->base_price_usd_cents / 100, 2) }}</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                        {{ $c->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-slate-400">Tidak ada kontrak aktif yang terdaftar untuk akun Anda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Ajukan Nominasi Pengapalan</h2>
            <form action="/offtaker/mining/nominate" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pilih Kontrak</label>
                    <select name="contract_id" class="w-full rounded-lg border-slate-300 border p-2.5 text-sm" required>
                        @foreach($contracts as $c)
                            <option value="{{ $c->id }}">{{ $c->contract_code ?? $c->contract_number }} ({{ $c->commodity }} - {{ number_format($c->contracted_tonnage_kg) }} kg)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Volume Nominasi (kg)</label>
                    <input type="number" name="quantity_kg" min="1000" class="w-full rounded-lg border-slate-300 border p-2.5 text-sm" placeholder="Contoh: 5000000" required>
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm transition">
                    Kirim Nominasi Pengapalan
                </button>
            </form>
        </section>
    </div>
</body>
</html>
