<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Bursa Perdagangan Karbon Internal Konglomerasi') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('egy.carbon.export') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh Transaksi (CSV)
                </a>
                <form action="{{ route('egy.carbon.match') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        ⚡ Eksekusi Match Engine
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Ringkasan Metrik Pasar -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-emerald-500">
                    <div class="text-sm font-medium text-gray-500">Total Volume Terperdagangkan</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($total_traded_kg ?? 0, 0, ',', '.') }} kg CO₂e</div>
                    <div class="mt-1 text-xs text-emerald-600">Diselesaikan lewat settlement ledger Banking</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="text-sm font-medium text-gray-500">Total Nilai Perdagangan</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">Rp {{ number_format($total_traded_value_idr ?? 0, 0, ',', '.') }}</div>
                    <div class="mt-1 text-xs text-indigo-600">Zero-Float minor integer settlement</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-amber-500">
                    <div class="text-sm font-medium text-gray-500">Proyek Karbon Terdaftar</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ count($projects ?? []) }} Proyek</div>
                    <div class="mt-1 text-xs text-amber-600">SRN-PPI, Verra VCS, Gold Standard</div>
                </div>
            </div>

            <!-- Visualisasi Kedalaman Pasar (Market Depth SVG) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Kurva Kedalaman Pasar Karbon (Market Depth Curve)
                </h3>
                <div class="w-full overflow-x-auto">
                    <svg class="w-full h-48" viewBox="0 0 800 160">
                        <defs>
                            <linearGradient id="bidGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.4"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.05"/>
                            </linearGradient>
                            <linearGradient id="askGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#ef4444" stop-opacity="0.4"/>
                                <stop offset="100%" stop-color="#ef4444" stop-opacity="0.05"/>
                            </linearGradient>
                        </defs>
                        <!-- Grid lines -->
                        <line x1="40" y1="20" x2="760" y2="20" stroke="#f3f4f6" stroke-width="1"/>
                        <line x1="40" y1="60" x2="760" y2="60" stroke="#f3f4f6" stroke-width="1"/>
                        <line x1="40" y1="100" x2="760" y2="100" stroke="#f3f4f6" stroke-width="1"/>
                        <line x1="40" y1="140" x2="760" y2="140" stroke="#e5e7eb" stroke-width="1.5"/>

                        <!-- Center Spread Divider -->
                        <line x1="400" y1="10" x2="400" y2="140" stroke="#6b7280" stroke-width="1.5" stroke-dasharray="4"/>
                        <text x="400" y="155" font-size="10" text-anchor="middle" fill="#6b7280">MID SPREAD (Rp 350.000 / ton)</text>

                        <!-- Bid Area (Left Green) -->
                        <path d="M 60,140 L 60,120 L 150,105 L 260,80 L 380,45 L 380,140 Z" fill="url(#bidGrad)" stroke="#10b981" stroke-width="2"/>
                        <text x="200" y="30" font-size="11" font-weight="bold" fill="#047857">PENAWARAN BELI (BIDS)</text>

                        <!-- Ask Area (Right Red) -->
                        <path d="M 420,140 L 420,45 L 530,75 L 640,100 L 740,115 L 740,140 Z" fill="url(#askGrad)" stroke="#ef4444" stroke-width="2"/>
                        <text x="600" y="30" font-size="11" font-weight="bold" fill="#b91c1c">PENAWARAN JUAL (ASKS)</text>
                    </svg>
                </div>
            </div>

            <!-- Form Pemesanan & Orderbook Dua Kolom -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Panel Form Entry Order -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-md font-bold text-gray-900 mb-4">Input Pesanan Limit</h3>
                    <form action="{{ route('egy.carbon.order.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Sisi Pesanan (Side)</label>
                            <select name="side" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="BUY">BELI (Kredit Karbon Masuk)</option>
                                <option value="SELL">JUAL (Kredit Karbon Keluar)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Tahun Vintage</label>
                            <input type="text" name="vintage_year" value="{{ date('Y') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="2026">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Kuantitas (kg CO₂e)</label>
                            <input type="number" name="quantity_kg_co2e" step="100" min="100" value="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Harga Batas (Rp per Ton)</label>
                            <input type="number" name="limit_price_minor_per_ton" step="1000" min="1000" value="350000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-md uppercase tracking-wider">
                            Daftarkan ke Orderbook
                        </button>
                    </form>
                </div>

                <!-- Buku Pesanan: Bids & Asks -->
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Kolom Bids -->
                        <div>
                            <div class="flex justify-between items-center pb-2 border-b border-emerald-200">
                                <span class="font-bold text-sm text-emerald-700">Bids (Penawaran Beli)</span>
                                <span class="text-xs text-gray-400">Harga Tertinggi Dulu</span>
                            </div>
                            <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                                @forelse($bids as $b)
                                    <div class="py-2 flex justify-between items-center text-xs">
                                        <span class="font-mono text-emerald-600 font-bold">Rp {{ number_format($b->limit_price_minor_per_ton, 0, ',', '.') }}</span>
                                        <span class="font-mono text-gray-700">{{ number_format($b->quantity_kg_co2e - $b->filled_kg_co2e, 0, ',', '.') }} kg</span>
                                        <span class="text-gray-400">#{{ $b->party_id }}</span>
                                    </div>
                                @empty
                                    <p class="py-4 text-center text-xs text-gray-400">Belum ada antrean bid aktif.</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Kolom Asks -->
                        <div>
                            <div class="flex justify-between items-center pb-2 border-b border-red-200">
                                <span class="font-bold text-sm text-red-700">Asks (Penawaran Jual)</span>
                                <span class="text-xs text-gray-400">Harga Terendah Dulu</span>
                            </div>
                            <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                                @forelse($asks as $a)
                                    <div class="py-2 flex justify-between items-center text-xs">
                                        <span class="font-mono text-red-600 font-bold">Rp {{ number_format($a->limit_price_minor_per_ton, 0, ',', '.') }}</span>
                                        <span class="font-mono text-gray-700">{{ number_format($a->quantity_kg_co2e - $a->filled_kg_co2e, 0, ',', '.') }} kg</span>
                                        <span class="text-gray-400">#{{ $a->party_id }}</span>
                                    </div>
                                @empty
                                    <p class="py-4 text-center text-xs text-gray-400">Belum ada antrean ask aktif.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Riwayat Eksekusi Transaksi Terakhir -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4">Riwayat Eksekusi Transaksi Pencocokan (Recent Trades)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">No. Transaksi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Pembeli</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Penjual</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Volume (kg)</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Harga Eksekusi</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Total Nominal</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Ledger Tx</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Sertifikat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recent_trades as $t)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">{{ $t->trade_number }}</td>
                                    <td class="px-4 py-3">Party #{{ $t->buyer_party_id }}</td>
                                    <td class="px-4 py-3">Party #{{ $t->seller_party_id }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($t->matched_kg_co2e, 0, ',', '.') }} kg</td>
                                    <td class="px-4 py-3 text-right font-mono">Rp {{ number_format($t->execution_price_minor_per_ton, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">Rp {{ number_format($t->total_amount_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center font-mono">#{{ $t->ledger_transaction_id ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center font-mono text-gray-400 truncate max-w-xs" title="{{ $t->certificate_hash }}">{{ substr($t->certificate_hash, 0, 10) }}…</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada riwayat transaksi eksekusi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
