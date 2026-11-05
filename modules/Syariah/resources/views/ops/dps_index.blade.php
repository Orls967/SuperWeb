<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pengawasan & Fatwa Dewan Pengawas Syariah (DPS)</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Jejak Pengawasan Four-Eyes Governance dan Rekomendasi Kepatuhan (G7-D23)</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Reviu</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Periode</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tipe Reviu</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Anggota DPS</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Hasil / Vonis</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Temuan & Rekomendasi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($reviews as $rev)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $rev->review_ref }}</td>
                            <td class="px-6 py-4">{{ $rev->period_label }}</td>
                            <td class="px-6 py-4">{{ $rev->review_type }}</td>
                            <td class="px-6 py-4 font-semibold">{{ $rev->reviewer_name }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $rev->verdict === 'COMPLIANT' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $rev->verdict }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div><span class="font-bold">Temuan:</span> {{ $rev->findings }}</div>
                                <div class="mt-1 text-gray-500"><span class="font-bold">Rekomendasi:</span> {{ $rev->recommendations }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">{{ $rev->reviewed_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">Belum ada catatan reviu DPS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reviews->links() }}</div>
    </div>
</x-app-layout>
