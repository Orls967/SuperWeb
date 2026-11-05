<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Detail Evaluasi NPF: {{ $classification->classification_ref }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Periode: {{ $classification->period_label }} &bull; Tanggal Evaluasi: {{ $classification->evaluation_date->format('d F Y') }}</p>
            </div>
            <a href="{{ route('syariah.ops.npf.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Daftar</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Total Portofolio</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">Rp {{ number_format($classification->total_portfolio_minor / 100, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">NPF Gross</div>
                <div class="text-2xl font-extrabold {{ $classification->is_compliant ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ number_format($classification->npf_gross_bps / 100, 2) }}%
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Total Cadangan CKPN</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">Rp {{ number_format($classification->total_required_provision_minor / 100, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Sertifikat Integritas SHA-256</div>
                <div class="text-xs font-mono text-gray-600 dark:text-gray-400 truncate mt-1" title="{{ $classification->certificate_hash }}">
                    {{ $classification->certificate_hash }}
                </div>
            </div>
        </div>

        <!-- Snapshots Table -->
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Daftar Kontrak dalam Portofolio</h2>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Kontrak</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nasabah</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Produk</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">DPD</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kolektibilitas</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Sisa Pokok</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Cadangan CKPN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($classification->snapshots as $s)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $s->contract_ref }}</td>
                            <td class="px-6 py-4">{{ $s->customer_ref }}</td>
                            <td class="px-6 py-4">{{ $s->product_type }}</td>
                            <td class="px-6 py-4 font-bold {{ $s->dpd > 90 ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">{{ $s->dpd }} hari</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs rounded-full font-semibold {{ $s->collectibility <= 2 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    Kol {{ $s->collectibility }} &bull; {{ $s->collectibility_name }}
                                </span>
                            </td>
                            <td class="px-6 py-4">Rp {{ number_format($s->outstanding_principal_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-semibold">Rp {{ number_format($s->provision_amount_minor / 100, 0, ',', '.') }} ({{ number_format($s->provision_rate_bps / 100, 1) }}%)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-6 text-center text-gray-500">Tidak ada snapshot kontrak tersimpan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
