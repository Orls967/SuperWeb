<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Release Train Orchestration') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Jadwal Release Train</h3>
                    <span class="text-sm text-gray-500">Total Gerbong: {{ $viewModel->totalTrains() }}</span>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Train</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versi</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release Manager</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jadwal Deploy</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($trains as $train)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    <a href="{{ route('platform.release.show', $train) }}">{{ $train->train_code }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $train->version }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $train->releaseManager->name ?? 'Unassigned' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ $train->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $train->scheduled_deploy_at ?? 'TBA' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada Release Train terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
