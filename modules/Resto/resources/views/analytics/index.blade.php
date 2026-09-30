<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Analitik & Menu Engineering
                </h2>
                <p class="text-sm text-slate-500 mt-1">Matriks BCG Profitabilitas Menu, Heatmap Jam Sibuk, Peramalan Bahan & Laba Rugi Terkonsolidasi</p>
            </div>
            <div>
                <form method="GET" action="{{ route('resto.analytics.index') }}" class="flex items-center gap-2">
                    <select name="outlet_id" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-semibold text-slate-700 focus:ring-teal-500 focus:border-teal-500">
                        <option value="">Semua Outlet (Konsolidasi)</option>
                        @foreach($outlets as $o)
                            <option value="{{ $o->id }}" {{ $selectedOutletId === $o->id ? 'selected' : '' }}>
                                {{ $o->name }} ({{ $o->code }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Laba Bersih Buku</span>
                        <div class="p-2 rounded-xl {{ $profitLoss['net_profit'] >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black {{ $profitLoss['net_profit'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                            Rp {{ number_format($profitLoss['net_profit'], 0, ',', '.') }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Pendapatan: Rp {{ number_format($profitLoss['total_revenue'], 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Menu Bintang (Stars)</span>
                        <div class="p-2 rounded-xl bg-amber-50 text-amber-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-slate-900">{{ $menuEngineering['quadrant_counts']['star'] }} Menu</div>
                        <p class="text-xs text-amber-600 font-semibold mt-1">Volume Tinggi &bull; Margin Tinggi</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Biaya & Beban Operasional</span>
                        <div class="p-2 rounded-xl bg-blue-50 text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-slate-900">
                            Rp {{ number_format($profitLoss['total_expense'], 0, ',', '.') }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">HPP & Biaya dari Buku Besar</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wider">Waste & Susut (30 Hari)</span>
                        <div class="p-2 rounded-xl bg-rose-50 text-rose-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-rose-600">
                            Rp {{ number_format($wasteReport['total_waste_value'], 0, ',', '.') }}
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Dari penutupan harian etalase & basi</p>
                    </div>
                </div>
            </div>

            <!-- BCG Matrix Menu Engineering Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">BCG Matrix Menu Engineering</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Klasifikasi performa menu berdasarkan rata-rata volume ({{ $menuEngineering['average_volume'] }} porsi) dan margin (Rp {{ number_format($menuEngineering['average_margin'], 0, ',', '.') }}/porsi)</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-xs font-bold">
                            ⭐ Stars: {{ $menuEngineering['quadrant_counts']['star'] }}
                        </span>
                        <span class="px-3 py-1 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-xs font-bold">
                            🐎 Plowhorses: {{ $menuEngineering['quadrant_counts']['plowhorse'] }}
                        </span>
                        <span class="px-3 py-1 bg-purple-50 border border-purple-200 text-purple-800 rounded-lg text-xs font-bold">
                            🧩 Puzzles: {{ $menuEngineering['quadrant_counts']['puzzle'] }}
                        </span>
                        <span class="px-3 py-1 bg-slate-100 border border-slate-200 text-slate-700 rounded-lg text-xs font-bold">
                            🐕 Dogs: {{ $menuEngineering['quadrant_counts']['dog'] }}
                        </span>
                    </div>
                </div>

                <!-- 4 Quadrants Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-amber-900 text-sm">⭐ Stars (Bintang)</span>
                            <span class="text-xs bg-amber-200 text-amber-900 px-2 py-0.5 rounded font-black">{{ $menuEngineering['quadrant_counts']['star'] }}</span>
                        </div>
                        <p class="text-xs text-amber-700 mb-2">Volume tinggi & Margin keuntungan tinggi.</p>
                        <p class="text-[11px] font-semibold text-amber-800 bg-amber-100/60 p-2 rounded">
                            Rekomendasi: Lindungi kualitas, letakkan di posisi terdepan etalase/menu.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl border border-blue-200 bg-blue-50/50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-blue-900 text-sm">🐎 Plowhorses (Kuda Beban)</span>
                            <span class="text-xs bg-blue-200 text-blue-900 px-2 py-0.5 rounded font-black">{{ $menuEngineering['quadrant_counts']['plowhorse'] }}</span>
                        </div>
                        <p class="text-xs text-blue-700 mb-2">Volume tinggi tapi Margin tipis di bawah rata-rata.</p>
                        <p class="text-[11px] font-semibold text-blue-800 bg-blue-100/60 p-2 rounded">
                            Rekomendasi: Naikkan harga sedikit atau negosiasi HPP bahan baku.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl border border-purple-200 bg-purple-50/50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-purple-900 text-sm">🧩 Puzzles (Teka-Teki)</span>
                            <span class="text-xs bg-purple-200 text-purple-900 px-2 py-0.5 rounded font-black">{{ $menuEngineering['quadrant_counts']['puzzle'] }}</span>
                        </div>
                        <p class="text-xs text-purple-700 mb-2">Margin tinggi menguntungkan, namun volume penjualan rendah.</p>
                        <p class="text-[11px] font-semibold text-purple-800 bg-purple-100/60 p-2 rounded">
                            Rekomendasi: Lakukan upsell oleh pelayan, promosi katering/delivery.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-slate-800 text-sm">🐕 Dogs (Anjing)</span>
                            <span class="text-xs bg-slate-200 text-slate-800 px-2 py-0.5 rounded font-black">{{ $menuEngineering['quadrant_counts']['dog'] }}</span>
                        </div>
                        <p class="text-xs text-slate-600 mb-2">Volume rendah dan Margin tipis di bawah standar.</p>
                        <p class="text-[11px] font-semibold text-slate-700 bg-slate-200/60 p-2 rounded">
                            Rekomendasi: Rombak resep, naikkan harga, atau pertimbangkan hapus dari menu.
                        </p>
                    </div>
                </div>

                <!-- Menu Items Engineering Table -->
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/80 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-3">Menu & Kategori</th>
                                <th class="p-3 text-center">Kuadran BCG</th>
                                <th class="p-3 text-right">Harga Jual</th>
                                <th class="p-3 text-right">Estimasi HPP</th>
                                <th class="p-3 text-right">Margin / Porsi</th>
                                <th class="p-3 text-center">Volume (Porsi)</th>
                                <th class="p-3 text-right">Total Margin Kotor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($menuEngineering['items'] as $row)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-3">
                                        <div class="font-bold text-slate-800">{{ $row['item']->name }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">{{ $row['item']->category?->name ?? 'Menu Reguler' }}</div>
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($row['quadrant'] === 'star')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 text-amber-900 border border-amber-200">
                                                ⭐ Star
                                            </span>
                                        @elseif($row['quadrant'] === 'plowhorse')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 text-blue-900 border border-blue-200">
                                                🐎 Plowhorse
                                            </span>
                                        @elseif($row['quadrant'] === 'puzzle')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-purple-100 text-purple-900 border border-purple-200">
                                                🧩 Puzzle
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-100 text-slate-700 border border-slate-200">
                                                🐕 Dog
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right font-medium text-slate-700">
                                        Rp {{ number_format($row['item']->price, 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 text-right font-mono text-xs text-slate-500">
                                        Rp {{ number_format($row['cost_per_portion'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-emerald-600">
                                        Rp {{ number_format($row['margin_per_portion'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 text-center font-bold {{ $row['volume'] >= $menuEngineering['average_volume'] ? 'text-teal-700' : 'text-slate-500' }}">
                                        {{ $row['volume'] }}
                                    </td>
                                    <td class="p-3 text-right font-black text-slate-900">
                                        Rp {{ number_format($row['total_margin'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-slate-400 italic">Belum ada data penjualan menu untuk periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Hourly Sales Heatmap & Peak Hours -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Hourly Sales Heatmap (Jam Ramai Resto)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Analisis pola kedatangan tamu: Puncak Makan Siang (11:00-14:00) vs Makan Malam (18:00-21:00)</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-12 gap-2 pt-2">
                    @foreach($hourlyHeatmap as $h)
                        @php
                            $maxSales = collect($hourlyHeatmap)->max('total_sales') ?: 1;
                            $intensity = round(($h['total_sales'] / $maxSales) * 100);
                        @endphp
                        <div class="p-2.5 rounded-xl border text-center transition-all
                            {{ $h['is_peak_lunch'] ? 'border-amber-300 bg-amber-50/70 ring-1 ring-amber-400' : '' }}
                            {{ $h['is_peak_dinner'] ? 'border-indigo-300 bg-indigo-50/70 ring-1 ring-indigo-400' : '' }}
                            {{ ! $h['is_peak_lunch'] && ! $h['is_peak_dinner'] ? 'border-slate-100 bg-slate-50/50' : '' }}">
                            <div class="text-[11px] font-bold text-slate-700 font-mono">{{ sprintf('%02d:00', $h['hour']) }}</div>
                            <div class="text-xs font-black text-slate-900 mt-1">
                                {{ $h['order_count'] }} <span class="text-[10px] font-normal text-slate-500">trx</span>
                            </div>
                            <div class="text-[10px] font-semibold text-slate-600 truncate mt-0.5">
                                {{ $h['total_sales'] > 0 ? 'Rp '.number_format($h['total_sales'] / 1000, 0).'k' : '-' }}
                            </div>
                            @if($h['is_peak_lunch'])
                                <span class="block mt-1 text-[9px] font-bold text-amber-700 bg-amber-200/60 rounded px-1">Siang</span>
                            @elseif($h['is_peak_dinner'])
                                <span class="block mt-1 text-[9px] font-bold text-indigo-700 bg-indigo-200/60 rounded px-1">Malam</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Two-column Section: Ingredient Forecast & Consolidated P&L -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- 7-Day Ingredient Requirement Forecast -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-lg font-bold text-slate-900">Peramalan Kebutuhan Bahan (7 Hari)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Moving average 4 minggu ke belakang untuk mencegah kehabisan stok</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-100">
                                    <th class="p-2.5">Bahan Baku</th>
                                    <th class="p-2.5 text-center">Stok Saat Ini</th>
                                    <th class="p-2.5 text-center">Burn Rate/Hari</th>
                                    <th class="p-2.5 text-center">Prediksi 7 Hari</th>
                                    <th class="p-2.5 text-right">Rekomendasi Order</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse(array_slice($forecast, 0, 8) as $f)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-2.5">
                                            <div class="font-bold text-slate-800">{{ $f['ingredient']->name }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $f['ingredient']->code }}</div>
                                        </td>
                                        <td class="p-2.5 text-center font-semibold text-slate-700">
                                            {{ (float) $f['current_stock'] }} {{ $f['ingredient']->base_unit }}
                                        </td>
                                        <td class="p-2.5 text-center text-slate-600">
                                            {{ $f['daily_burn_rate'] }} /hari
                                        </td>
                                        <td class="p-2.5 text-center font-bold text-slate-800">
                                            {{ $f['forecast_7_days'] }}
                                        </td>
                                        <td class="p-2.5 text-right">
                                            @if($f['recommended_order'] > 0)
                                                <span class="inline-flex px-2 py-0.5 rounded font-black text-rose-700 bg-rose-50 border border-rose-200">
                                                    +{{ $f['recommended_order'] }} {{ $f['ingredient']->base_unit }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 font-medium">Aman</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-6 text-center text-slate-400 italic">Pilih outlet untuk melihat peramalan bahan baku.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Consolidated Profit & Loss from Ledger -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Laba Rugi Buku Besar (P&L Ledger)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Saldo riil akun nominal pendapatan & beban akuntansi</p>
                        </div>
                    </div>

                    <div class="space-y-4 text-sm">
                        <!-- Pendapatan -->
                        <div>
                            <div class="flex items-center justify-between font-bold text-slate-800 pb-1 border-b border-slate-100">
                                <span>Total Pendapatan (Revenue)</span>
                                <span class="text-emerald-700 font-mono">Rp {{ number_format($profitLoss['total_revenue'], 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-2 space-y-1 pl-2">
                                @forelse($profitLoss['revenues'] as $code => $amt)
                                    <div class="flex items-center justify-between text-xs text-slate-600">
                                        <span class="font-mono text-slate-500">{{ $code }}</span>
                                        <span class="font-medium">Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400 italic">Belum ada mutasi pendapatan</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Beban -->
                        <div>
                            <div class="flex items-center justify-between font-bold text-slate-800 pb-1 border-b border-slate-100">
                                <span>Total Beban Operasional & HPP</span>
                                <span class="text-rose-700 font-mono">Rp {{ number_format($profitLoss['total_expense'], 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-2 space-y-1 pl-2">
                                @forelse($profitLoss['expenses'] as $code => $amt)
                                    <div class="flex items-center justify-between text-xs text-slate-600">
                                        <span class="font-mono text-slate-500">{{ $code }}</span>
                                        <span class="font-medium">Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400 italic">Belum ada mutasi beban</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Laba Bersih -->
                        <div class="pt-3 border-t-2 border-slate-200 flex items-center justify-between text-base font-black">
                            <span class="text-slate-900">LABA / (RUGI) BERSIH</span>
                            <span class="{{ $profitLoss['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">
                                Rp {{ number_format($profitLoss['net_profit'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
