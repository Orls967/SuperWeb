<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Syariah Operations & Compliance Command Center</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Pusat pemantauan portofolio pembiayaan, NPF, transaksi dompet 30 lini, dan kepatuhan DPS</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('syariah.ops.npf.index') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">Laporan NPF</a>
                <a href="{{ route('syariah.ops.restructuring.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Restrukturisasi</a>
                <a href="{{ route('syariah.ops.wallet.index') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">Dompet 30 Lini</a>
                <a href="{{ route('syariah.ops.dps.index') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium">Review DPS</a>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Rasio NPF Gross</div>
                <div class="text-3xl font-extrabold {{ $metrics['portfolio']['is_compliant'] ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ number_format($metrics['portfolio']['npf_gross_bps'] / 100, 2) }}%
                </div>
                <div class="text-xs text-gray-500 mt-2">Batas OJK: &le; 5.00% &bull; Net: {{ number_format($metrics['portfolio']['npf_net_bps'] / 100, 2) }}%</div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Portofolio Pembiayaan</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    Rp {{ number_format($metrics['portfolio']['total_portfolio_minor'] / 100, 0, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 mt-2">{{ $metrics['portfolio']['total_contracts'] }} Kontrak Aktif</div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Volume Dompet 30 Lini</div>
                <div class="text-2xl font-bold text-blue-600">
                    Rp {{ number_format($metrics['wallet_30_lines']['total_volume_minor'] / 100, 0, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 mt-2">{{ $metrics['wallet_30_lines']['total_transactions'] }} Transaksi Lolos Halal</div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Cadangan CKPN Terbentuk</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    Rp {{ number_format($metrics['portfolio']['total_provision_minor'] / 100, 0, ',', '.') }}
                </div>
                <div class="text-xs text-emerald-600 mt-2">&check; Sesuai Ketentuan POJK</div>
            </div>
        </div>

        <!-- Section Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Status Kepatuhan Halal Merchant 30 Lini</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Merchant Halal Aktif</span>
                        <span class="text-sm font-bold text-emerald-600">{{ $metrics['wallet_30_lines']['active_halal_merchants'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Sertifikat Kedaluwarsa</span>
                        <span class="text-sm font-bold text-amber-600">{{ $metrics['wallet_30_lines']['expired_certificates'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Merchant Masuk Daftar Hitam (Blacklist)</span>
                        <span class="text-sm font-bold text-rose-600">{{ $metrics['wallet_30_lines']['blacklisted_merchants'] }}</span>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="{{ route('syariah.ops.merchants.index') }}" class="text-sm text-emerald-600 hover:underline font-medium">&rarr; Kelola Registri Merchant Halal</a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Pengawasan Operasional & DPS</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Permohonan Restrukturisasi Menunggu</span>
                        <span class="text-sm font-bold text-indigo-600">{{ $metrics['restructuring']['pending_approval'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Restrukturisasi Terlaksana (Murni Bebas Bunga)</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $metrics['restructuring']['total_executed'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Insiden Terbuka</span>
                        <span class="text-sm font-bold {{ $metrics['compliance']['open_incidents'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $metrics['compliance']['open_incidents'] }}</span>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="{{ route('syariah.ops.incidents.index') }}" class="text-sm text-indigo-600 hover:underline font-medium">&rarr; Lihat Log Insiden Kepatuhan</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
