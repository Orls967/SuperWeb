<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Release Train: ') }} {{ $train->train_code }} ({{ $train->version }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-4 border-b pb-4 mb-4">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Versi Target:</span>
                        <span class="text-sm font-bold text-gray-900">{{ $train->version }}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Status Gerbong:</span>
                        <span class="text-sm font-bold text-green-600">{{ $train->status }}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Batas Freeze:</span>
                        <span class="text-sm text-gray-800">{{ $train->freeze_at ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Rencana Deploy:</span>
                        <span class="text-sm text-gray-800">{{ $train->scheduled_deploy_at ?? 'N/A' }}</span>
                    </div>
                </div>

                <h4 class="text-md font-bold text-gray-900 mb-2">Change Requests Terkait</h4>
                <ul class="list-disc pl-5 mb-6 text-sm text-gray-700">
                    @forelse ($train->changeRequests as $cr)
                        <li>[{{ $cr->reference_number }}] {{ $cr->title }} ({{ $cr->target_module }}) - {{ $cr->status }}</li>
                    @empty
                        <li class="text-gray-400">Belum ada tiket perubahan yang dimuat.</li>
                    @endforelse
                </ul>

                <h4 class="text-md font-bold text-gray-900 mb-2">Hotfixes Darurat</h4>
                <ul class="list-disc pl-5 text-sm text-gray-700">
                    @forelse ($train->hotfixes as $hf)
                        <li>[{{ $hf->hotfix_number }}] {{ $hf->severity }} - Status isolasi: {{ $hf->isolation_status }}</li>
                    @empty
                        <li class="text-gray-400">Nihil hotfix darurat.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>

