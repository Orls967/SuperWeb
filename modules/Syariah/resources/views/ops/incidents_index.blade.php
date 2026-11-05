<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Log Insiden Kepatuhan Operasional Syariah</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Pencatatan Anomali, Kebocoran Non-Halal & Mitigasi Risiko Reputasi</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Insiden</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tingkat Keparahan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kategori</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Deskripsi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Entitas Terkait</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($incidents as $inc)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $inc->incident_ref }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs rounded-full font-bold {{ $inc->severity === 'CRITICAL' ? 'bg-rose-100 text-rose-800' : ($inc->severity === 'HIGH' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ $inc->severity }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold">{{ $inc->category }}</td>
                            <td class="px-6 py-4 text-xs">{{ $inc->description }}</td>
                            <td class="px-6 py-4 font-mono text-xs">{{ $inc->entity_reference }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $inc->resolution_status === 'RESOLVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $inc->resolution_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada log insiden operasional.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $incidents->links() }}</div>
    </div>
</x-app-layout>
