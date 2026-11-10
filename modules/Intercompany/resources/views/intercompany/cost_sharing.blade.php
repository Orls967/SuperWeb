<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Perjanjian Pembagian Biaya & Jasa Manajemen (Cost Sharing)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Perjanjian Pembagian Beban Bersama (CSA)</h3>
                        <p class="text-xs text-gray-500 mt-1">Alokasi biaya layanan bersama (Shared Services, Legal, TI, HR) berdasar proporsi rasio permil.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-teal-100 text-teal-800">
                        OECD Chapter VI / PMK-172
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Perjanjian</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Layanan</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penyedia (Provider)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pool Biaya (IDR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Markup Jasa</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Berlaku</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($agreements as $agr)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $agr->agreement_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $agr->service_type }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $agr->provider_entity }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($agr->total_cost_pool_idr) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-sm text-gray-700">{{ number_format($agr->markup_bps / 100, 2) }}%</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $agr->effective_date ? $agr->effective_date->format('Y-m-d') : '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">
                                            {{ $agr->is_active ? 'AKTIF' : 'NON-AKTIF' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada perjanjian pembagian biaya tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

