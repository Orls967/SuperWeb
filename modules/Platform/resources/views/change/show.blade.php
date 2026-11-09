<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Change Request: ') }} {{ $change->reference_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $change->title }}</h3>
                <p class="text-gray-600 mb-4">{{ $change->description }}</p>

                <div class="grid grid-cols-2 gap-4 border-t border-b py-4">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Target Modul:</span>
                        <span class="text-sm font-bold text-gray-800">{{ $change->target_module }}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Status Saat Ini:</span>
                        <span class="text-sm font-bold text-blue-600">{{ $change->status }}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Skor Risiko:</span>
                        <span class="text-sm font-bold text-amber-600">{{ $change->risk_score / 100 }} ({{ $change->risk_tier }})</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Pengaju:</span>
                        <span class="text-sm text-gray-800">{{ $change->requester->name ?? 'System' }}</span>
                    </div>
                </div>

                <h4 class="text-md font-bold text-gray-900 mt-6 mb-3">Jejak Audit Transisi</h4>
                <ul class="divide-y divide-gray-100">
                    @forelse ($change->transitions as $transition)
                        <li class="py-2 text-sm">
                            <span class="font-semibold text-gray-700">{{ $transition->from_status }} &rarr; {{ $transition->to_status }}</span>
                            <span class="text-gray-500 text-xs ml-2">oleh {{ $transition->operator->name ?? 'User' }} pada {{ $transition->created_at }}</span>
                            @if ($transition->reason)
                                <p class="text-gray-600 text-xs italic mt-1">Alasan: {{ $transition->reason }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-400">Belum ada riwayat transisi tercatat.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>

