@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Alokasi Rak & Port Colocation</h2>
                    <p class="mt-1 text-sm text-gray-400">Status Port Jaringan, Konsumsi Listrik & Penagihan Rak Data Center</p>
                </div>
                <a href="{{ route('tlx.datacenter.index') }}" class="text-sm text-indigo-400 hover:underline">&larr; Kembali ke Data Centers</a>
            </div>

            <div class="bg-gray-700/50 p-6 rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-800 text-gray-400 text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3">No. Kontrak</th>
                                <th class="px-4 py-3">Tenant</th>
                                <th class="px-4 py-3">Unit Rak (RU)</th>
                                <th class="px-4 py-3">Biaya Bulanan</th>
                                <th class="px-4 py-3">Status Port</th>
                                <th class="px-4 py-3">Status Overdue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($contracts as $c)
                                <tr class="hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-mono font-medium text-white">{{ $c->contract_number }}</td>
                                    <td class="px-4 py-3">{{ $c->tenant_party_id }}</td>
                                    <td class="px-4 py-3">{{ $c->rack_units_allocated }} RU</td>
                                    <td class="px-4 py-3 text-emerald-400">Rp {{ number_format($c->monthly_rack_fee_minor, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $c->port_status === 'UP' ? 'bg-emerald-900 text-emerald-200' : 'bg-amber-900 text-amber-200' }}">
                                            {{ $c->port_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $c->is_overdue ? 'bg-rose-900 text-rose-200' : 'bg-gray-800 text-gray-300' }}">
                                            {{ $c->is_overdue ? 'YES' : 'NO' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada kontrak alokasi rak.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
