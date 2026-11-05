<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pemulihan Pembiayaan Macet & Lelang Agunan Syariah</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Pengelolaan Hapus Buku (Write-off), Lelang Marhun & Pemulihan Cadangan (Fatwa DSN-MUI)</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <!-- Hapus Buku Table -->
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Daftar Penghapusbukuan (Write-Off) Berizin DPS</h2>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto mb-8">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Pembiayaan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nasabah</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Pokok Hapus Buku</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Margin Hapus Buku</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Izin DPS & Direksi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($writeoffs as $w)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $w->financing_ref }}</td>
                            <td class="px-6 py-4">{{ $w->customer_ref }}</td>
                            <td class="px-6 py-4 font-bold text-rose-600">Rp {{ number_format($w->written_off_principal_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($w->written_off_margin_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-xs font-mono">{{ $w->dps_approval_ref ?? 'MENUNGGU DPS' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $w->status === 'EXECUTED' ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $w->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-6 text-center text-gray-500">Belum ada data penghapusbukuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Lelang Agunan Table -->
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Lelang Agunan (Marhun) & Pengembalian Kelebihan Hak Nasabah</h2>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Lelang</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Deskripsi Agunan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nilai Taksasi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tawaran Menang</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kelebihan Dikembalikan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($auctions as $auc)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $auc->auction_ref }}</td>
                            <td class="px-6 py-4">{{ $auc->collateral_description }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($auc->appraised_value_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-bold text-emerald-600">Rp {{ number_format($auc->winning_bid_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-semibold text-blue-600">Rp {{ number_format($auc->surplus_refunded_to_customer_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $auc->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $auc->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-6 text-center text-gray-500">Belum ada jadwal lelang agunan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
