<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Restrukturisasi Pembiayaan Syariah</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Penataan Kembali Akad Tanpa Bunga / Tambahan Margin Ilegal (G7-D19)</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Permohonan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Pembiayaan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nasabah</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Pokok Baru</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Margin Baru</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tenor Baru</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($requests as $r)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $r->request_ref }}</td>
                            <td class="px-6 py-4 font-mono">{{ $r->financing_ref }}</td>
                            <td class="px-6 py-4">{{ $r->customer_ref }}</td>
                            <td class="px-6 py-4 font-semibold">Rp {{ number_format($r->revised_principal_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($r->revised_margin_minor / 100, 0, ',', '.') }} (Haircut: {{ number_format($r->margin_haircut_bps / 100, 1) }}%)</td>
                            <td class="px-6 py-4">{{ $r->new_tenor_months }} bln (Grace: {{ $r->grace_period_months }} bln)</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold 
                                    {{ $r->status === 'EXECUTED' ? 'bg-emerald-100 text-emerald-800' : ($r->status === 'APPROVED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $r->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('syariah.ops.restructuring.show', $r->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">Belum ada permohonan restrukturisasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    </div>
</x-app-layout>
