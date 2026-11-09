<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Developer Experience & Platform Portal') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Sandbox Section -->
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Sandbox Environments') }}</h3>
                <p class="text-sm text-gray-600 mb-4">Lingkungan pengujian terisolasi dengan TTL otomatis dan perlindungan PII.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @forelse($sandboxes as $sandbox)
                        <div class="border rounded-lg p-4 bg-slate-50">
                            <div class="font-semibold text-slate-800">{{ $sandbox->name }}</div>
                            <div class="text-xs text-slate-500 mt-1">Status: <span class="font-bold text-sky-600">{{ $sandbox->status }}</span></div>
                            <div class="text-xs text-slate-500 mt-1">Expires: {{ $sandbox->expires_at?->diffForHumans() ?? 'Permanent' }}</div>
                            <a href="{{ route('platform.dev.sandboxes.show', $sandbox) }}" class="mt-3 inline-block text-xs text-indigo-600 hover:underline">Detail Sandbox &rarr;</a>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500 col-span-3">Belum ada sandbox aktif.</div>
                    @endforelse
                </div>
            </div>

            <!-- Quality Section -->
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Quality Ratchet Metrics') }}</h3>
                <p class="text-sm text-gray-600 mb-4">Monitoring integritas kode, deteksi padding (M-2 s/d M-5), dan sertifikat algoritma.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fase</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">B1 LOC</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Decision Density</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duplicate Ratio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Orphans</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($metrics as $metric)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $metric->phase_id }}</td>
                                    <td class="px-4 py-2">{{ number_format($metric->b1_loc) }}</td>
                                    <td class="px-4 py-2">{{ number_format($metric->decision_density, 4) }}</td>
                                    <td class="px-4 py-2">{{ number_format($metric->duplicate_ratio * 100, 2) }}%</td>
                                    <td class="px-4 py-2">{{ $metric->orphan_count }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs rounded {{ $metric->status === 'compliant' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $metric->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-2 text-center text-gray-500">Belum ada snapshot metrik tersimpan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

