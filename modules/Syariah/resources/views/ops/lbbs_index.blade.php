<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Laporan Bulanan Bank Syariah (LBBS OJK)</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Periode: {{ $report['period'] }} &bull; Digital Digest: <span class="font-mono text-xs">{{ substr($report['report_hash'], 0, 16) }}...</span></p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <!-- Prudential Ratios -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">NPF Gross OJK</div>
                <div class="text-3xl font-extrabold {{ $report['portfolio_summary']['is_npf_compliant'] ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $report['portfolio_summary']['npf_gross_ratio_pct'] }}%
                </div>
                <div class="text-xs text-gray-500 mt-1">Batas Maksimal: 5.00%</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">NPF Coverage (CKPN)</div>
                <div class="text-3xl font-bold text-emerald-600">
                    {{ $report['portfolio_summary']['npf_coverage_ratio_pct'] }}%
                </div>
                <div class="text-xs text-gray-500 mt-1">Cadangan Kerugian Penurunan Nilai</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">FDR Syariah</div>
                <div class="text-3xl font-bold text-indigo-600">
                    {{ $report['prudential_ratios']['fdr_pct'] }}%
                </div>
                <div class="text-xs text-gray-500 mt-1">Target Regulasi: 78% - 92%</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">CAR (Kecukupan Modal)</div>
                <div class="text-3xl font-bold text-blue-600">
                    {{ $report['prudential_ratios']['car_pct'] }}%
                </div>
                <div class="text-xs text-gray-500 mt-1">Batas Minimum: &ge; 8.00%</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Ringkasan Portofolio & Restrukturisasi</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div class="space-y-3">
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Total Pembiayaan Disalurkan:</span>
                        <span class="font-bold">Rp {{ number_format($report['portfolio_summary']['total_financing_portfolio_minor'] / 100, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Pembiayaan Lancar (Performing):</span>
                        <span class="font-semibold text-emerald-600">Rp {{ number_format($report['portfolio_summary']['current_performing_minor'] / 100, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Pembiayaan Bermasalah (NPF):</span>
                        <span class="font-semibold text-rose-600">Rp {{ number_format($report['portfolio_summary']['non_performing_minor'] / 100, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Portofolio Direstrukturisasi:</span>
                        <span class="font-bold">Rp {{ number_format($report['restructuring_profile']['total_restructured_portfolio_minor'] / 100, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Jumlah Kontrak Restrukturisasi:</span>
                        <span class="font-semibold">{{ $report['restructuring_profile']['restructured_contract_count'] }} Kontrak</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Volume Transaksi Dompet 30 Lini:</span>
                        <span class="font-semibold text-indigo-600">Rp {{ number_format($report['super_app_integration']['universal_wallet_gross_volume_minor'] / 100, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
