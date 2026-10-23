<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Wave Picking &amp; Outbound Allocation</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Alokasi Outbound Wave Picking</h3>
                        <p class="text-sm text-gray-500">Eksekusi wave release dengan strategi FIFO, FEFO lot expiry, atau pemisahan zona pemenuhan.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Total Wave</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">{{ count($waves ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Tugas Picking Aktif</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">{{ count($tasks ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-teal-50 border border-teal-100 rounded-lg">
                        <span class="text-xs font-semibold text-teal-600 uppercase">Strategi Teratas</span>
                        <p class="text-2xl font-black text-teal-900 mt-1">FEFO Expiry Guard</p>
                    </div>
                    <div class="p-4 bg-rose-50 border border-rose-100 rounded-lg">
                        <span class="text-xs font-semibold text-rose-600 uppercase">SLA Fulfillment</span>
                        <p class="text-2xl font-black text-rose-900 mt-1">99.4%</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">ID Wave</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Strategi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Waktu Rilis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($waves ?? [] as $wave)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">WAVE-{{ $wave->id }}</td>
                                    <td class="px-4 py-3 uppercase text-xs font-bold tracking-wider text-indigo-600">{{ $wave->strategy }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $wave->status === 'released' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $wave->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $wave->released_at ?? $wave->created_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada outbound wave aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
