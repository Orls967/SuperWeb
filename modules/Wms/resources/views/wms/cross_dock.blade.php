<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Cross-Docking &amp; Dock Management</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Manajemen Dermaga &amp; Cross-Docking Langsung</h3>
                <p class="text-sm text-gray-500 mb-4">Transfer cepat dari kontainer inbound langsung ke staging outbound untuk meminimalkan waktu tinggal dan biaya penanganan gudang.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Jadwal Dock Aktif</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">{{ count($docks ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-xs font-semibold text-blue-600 uppercase">Gudang Jaringan</span>
                        <p class="text-2xl font-black text-blue-900 mt-1">{{ count($warehouses ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-violet-50 border border-violet-100 rounded-lg">
                        <span class="text-xs font-semibold text-violet-600 uppercase">Rata-rata Waktu Tinggal</span>
                        <p class="text-2xl font-black text-violet-900 mt-1">&lt; 2.5 Jam</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Dermaga</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Referensi Inbound</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Arah</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Jendela Waktu</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($docks ?? [] as $dock)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">DOCK-{{ $dock->dock_door }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $dock->reference }}</td>
                                    <td class="px-4 py-3 capitalize text-gray-600">{{ $dock->direction }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $dock->window_start }} - {{ $dock->window_end }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                            {{ $dock->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada janji temu dermaga / cross-dock.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
