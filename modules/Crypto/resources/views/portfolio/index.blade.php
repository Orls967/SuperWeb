<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold bg-gradient-to-r from-amber-400 via-orange-400 to-yellow-500 bg-clip-text text-transparent flex items-center gap-2">
                    <svg class="w-7 h-7 text-amber-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Portofolio Kripto & Aset
                </h2>
                <p class="text-sm text-gray-400 mt-1">Ringkasan kepemilikan aset kripto, kalkulasi unrealized P/L, dan riwayat transaksi ledger.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('crypto.market.index') }}" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl text-sm font-bold flex items-center gap-2 transition duration-200 shadow-lg shadow-amber-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Beli & Jual di Pasar
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Chart.js CDN for Allocation Chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-8" x-data="portfolioView({{ json_encode($summary['allocations']) }})">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Total Net Worth -->
                <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition"></div>
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Kekayaan Bersih</div>
                    <div class="text-3xl font-black text-white font-mono mt-2">
                        Rp {{ $summary['total_net_worth_formatted'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-2 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Kripto + Saldo Dompet IDR
                    </div>
                </div>

                <!-- Total Crypto Value -->
                <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition"></div>
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Nilai Aset Kripto</div>
                    <div class="text-3xl font-black text-amber-400 font-mono mt-2">
                        Rp {{ $summary['total_crypto_value_formatted'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                        Tersebar di {{ count(array_filter($summary['items'], fn($i) => (float)$i['holding'] > 0)) }} aset aktif
                    </div>
                </div>

                <!-- IDR Wallet Cash Balance -->
                <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition"></div>
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Saldo Kas IDR (Dompet)</div>
                    <div class="text-3xl font-black text-emerald-400 font-mono mt-2">
                        Rp {{ $summary['idr_balance_formatted'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                        <a href="{{ route('wallet.index') }}" class="text-indigo-400 hover:text-indigo-300 underline">Kelola di Core Banking &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Allocation & Visual Breakdown -->
            @if(count($summary['allocations']) > 0)
                <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                    <div class="space-y-3">
                        <h3 class="text-base font-bold text-white">Alokasi Aset Portofolio</h3>
                        <p class="text-xs text-gray-400">Diversifikasi portofolio Anda antara koin kripto dan cadangan kas IDR.</p>
                        <div class="space-y-2 pt-2">
                            @foreach ($summary['allocations'] as $alloc)
                                <div class="flex items-center justify-between text-xs font-mono">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $alloc['color'] }};"></span>
                                        <span class="text-gray-300 font-semibold">{{ $alloc['symbol'] }}</span>
                                    </div>
                                    <span class="text-gray-400">{{ $alloc['percentage'] }}% (Rp {{ number_format((float)$alloc['value_idr'], 0, ',', '.') }})</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="lg:col-span-2 relative h-56 flex items-center justify-center">
                        <canvas id="allocationChart"></canvas>
                    </div>
                </div>
            @endif

            <!-- Holdings Table -->
            <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800/80 pb-4">
                    <h3 class="text-base font-bold text-white">Daftar Kepemilikan (Holdings)</h3>
                    <span class="text-xs text-gray-400 font-mono">P/L dihitung berdasarkan rata-rata harga beli</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-gray-400 uppercase bg-gray-800/40 border-b border-gray-800 font-semibold">
                            <tr>
                                <th class="px-4 py-3">Aset</th>
                                <th class="px-4 py-3">Saldo Koin</th>
                                <th class="px-4 py-3">Harga Pasar</th>
                                <th class="px-4 py-3">Nilai Sekarang</th>
                                <th class="px-4 py-3">Rata-Rata Beli</th>
                                <th class="px-4 py-3">Unrealized P/L</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60 font-mono text-xs">
                            @foreach ($summary['items'] as $item)
                                <tr class="hover:bg-gray-800/30 transition">
                                    <td class="px-4 py-4 font-sans">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-8 h-8 rounded-lg bg-gray-800 border border-gray-700 text-amber-400 font-bold flex items-center justify-center text-xs">
                                                {{ $item['symbol'] }}
                                            </span>
                                            <div>
                                                <div class="font-bold text-white">{{ $item['name'] }}</div>
                                                <div class="text-gray-400 text-2xs">{{ $item['symbol'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 font-bold text-white">
                                        {{ $item['holding_formatted'] }}
                                    </td>
                                    <td class="px-4 py-4 text-gray-300">
                                        Rp {{ $item['current_price_formatted'] }}
                                    </td>
                                    <td class="px-4 py-4 font-bold text-amber-400">
                                        Rp {{ $item['current_value_formatted'] }}
                                    </td>
                                    <td class="px-4 py-4 text-gray-400">
                                        {{ (float)$item['avg_buy_price'] > 0 ? 'Rp ' . $item['avg_buy_price_formatted'] : '-' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @if((float)$item['holding'] > 0 && (float)$item['avg_buy_price'] > 0)
                                            <div class="{{ $item['pnl_percent'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} font-bold">
                                                {{ $item['pnl_percent'] >= 0 ? '+' : '' }}Rp {{ $item['pnl_idr_formatted'] }}
                                                <span class="text-2xs font-normal">({{ $item['pnl_percent'] >= 0 ? '+' : '' }}{{ $item['pnl_percent'] }}%)</span>
                                            </div>
                                        @else
                                            <span class="text-gray-500">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('crypto.market.show', $item['symbol']) }}" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-amber-400 border border-amber-500/20 rounded-lg text-xs font-sans font-semibold transition">
                                            Trade &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Trades History Table -->
            <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800/80 pb-4">
                    <h3 class="text-base font-bold text-white">Riwayat Transaksi Trade Kripto</h3>
                    <span class="text-xs text-gray-400">Tercatat permanen dalam Double-Entry Ledger</span>
                </div>

                @if($summary['recent_trades']->isEmpty())
                    <div class="text-center py-8 text-gray-500 text-xs font-mono">
                        Belum ada riwayat transaksi perdagangan kripto.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-mono text-xs">
                            <thead class="text-2xs text-gray-400 uppercase bg-gray-800/40 border-b border-gray-800">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Aset</th>
                                    <th class="px-4 py-3">Tipe</th>
                                    <th class="px-4 py-3">Jumlah Koin</th>
                                    <th class="px-4 py-3">Harga Pasar</th>
                                    <th class="px-4 py-3">Total Nilai</th>
                                    <th class="px-4 py-3">Biaya Admin (0.2%)</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/60">
                                @foreach ($summary['recent_trades'] as $trade)
                                    <tr class="hover:bg-gray-800/30 transition">
                                        <td class="px-4 py-3 text-gray-400">
                                            {{ $trade->created_at->format('d M Y H:i:s') }}
                                        </td>
                                        <td class="px-4 py-3 font-bold text-white">
                                            {{ $trade->asset->symbol }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-2xs font-semibold
                                                {{ $trade->side->value === 'buy' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                                                {{ $trade->side->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-white">
                                            {{ $trade->quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-300">
                                            Rp {{ number_format((float)$trade->price_idr, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 font-bold text-amber-400">
                                            Rp {{ number_format((float)$trade->gross_idr, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-400">
                                            Rp {{ number_format((float)$trade->fee_idr, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-emerald-400 font-semibold text-2xs">Lengkap</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        function portfolioView(allocations) {
            return {
                init() {
                    if (!allocations || allocations.length === 0) return;

                    const ctx = document.getElementById('allocationChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: allocations.map(a => a.symbol),
                            datasets: [{
                                data: allocations.map(a => a.percentage),
                                backgroundColor: allocations.map(a => a.color),
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: '#D1D5DB',
                                        boxWidth: 12,
                                        font: { size: 11 }
                                    }
                                }
                            },
                            cutout: '70%'
                        }
                    });
                }
            };
        }
    </script>
</x-app-layout>
