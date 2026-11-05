<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Registri Merchant Halal 30 Lini</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Verifikasi Sertifikat Halal BPJPH/MUI & Filter Kategori Terlarang (G7-D22)</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kode Merchant</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nama Usaha</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Lini</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kategori</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nomor Sertifikat</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Masa Berlaku</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($merchants as $m)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $m->merchant_code }}</td>
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">{{ $m->name }}</td>
                            <td class="px-6 py-4">{{ $m->line_code }}</td>
                            <td class="px-6 py-4">{{ $m->category_code }}</td>
                            <td class="px-6 py-4 font-mono text-xs">{{ $m->certificate_number ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs">{{ $m->expires_at ? $m->expires_at->format('d/m/Y') : '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $m->status === 'ACTIVE' && !$m->is_blacklisted ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $m->is_blacklisted ? 'BLACKLIST' : $m->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">Belum ada merchant terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $merchants->links() }}</div>
    </div>
</x-app-layout>
