<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Change Management Governance') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="text-sm font-medium text-blue-600">Total Pengajuan Perubahan</div>
                        <div class="text-2xl font-bold">{{ $viewModel->totalChanges() }}</div>
                    </div>
                    <div class="p-4 bg-amber-50 rounded-lg">
                        <div class="text-sm font-medium text-amber-600">Perubahan Risiko Tinggi</div>
                        <div class="text-2xl font-bold">{{ $viewModel->highRiskCount() }}</div>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <div class="text-sm font-medium text-purple-600">Menunggu Tinjauan CAB</div>
                        <div class="text-2xl font-bold">{{ $viewModel->pendingCabReviewCount() }}</div>
                    </div>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Referensi</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modul</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier Risiko</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($changes as $change)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    <a href="{{ route('platform.change.show', $change) }}">{{ $change->reference_number }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $change->title }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $change->target_module }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $change->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        {{ $change->risk_tier }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada tiket Change Request terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $changes->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
