<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Klasifikasi Portofolio NPF Syariah</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Pemantauan Kolektibilitas 1–5 dan Cadangan Kerugian Penurunan Nilai (CKPN)</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Evaluasi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Periode</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Total Portofolio</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">NPF Gross</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">NPF Net</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Cadangan CKPN</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kepatuhan OJK</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($classifications as $row)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $row->classification_ref }}</td>
                            <td class="px-6 py-4">{{ $row->period_label }} ({{ $row->evaluation_date->format('d/m/Y') }})</td>
                            <td class="px-6 py-4 font-semibold">Rp {{ number_format($row->total_portfolio_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-bold {{ $row->is_compliant ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ number_format($row->npf_gross_bps / 100, 2) }}%
                            </td>
                            <td class="px-6 py-4">{{ number_format($row->npf_net_bps / 100, 2) }}%</td>
                            <td class="px-6 py-4">Rp {{ number_format($row->total_required_provision_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $row->is_compliant ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $row->is_compliant ? 'LULUS (<=5%)' : 'ELEVATED (>5%)' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('syariah.ops.npf.show', $row->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">Belum ada riwayat evaluasi NPF.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $classifications->links() }}</div>
    </div>
</x-app-layout>
