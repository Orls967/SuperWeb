<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Supervisi Portal Distributor — Pilih Distributor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                Pilih distributor untuk membuka portal dalam mode supervisi administrator. Akses akan dicatat pada audit log sistem.
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daftar Distributor</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-3 text-left font-medium text-gray-500">Kode</th>
                                <th class="p-3 text-left font-medium text-gray-500">Nama Distributor</th>
                                <th class="p-3 text-left font-medium text-gray-500">Tier</th>
                                <th class="p-3 text-left font-medium text-gray-500">Status</th>
                                <th class="p-3 text-right font-medium text-gray-500">Limit Kredit</th>
                                <th class="p-3 text-right font-medium text-gray-500">Eksposur</th>
                                <th class="p-3 text-center font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($distributors as $d)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold text-indigo-600">{{ $d->code }}</td>
                                    <td class="p-3 text-gray-900">{{ $d->name }}</td>
                                    <td class="p-3 uppercase text-xs font-bold text-gray-600">{{ $d->tier }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $d->status === 'approved' ? 'bg-green-100 text-green-800' : ($d->status === 'blocked' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $d->status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right text-gray-700">Rp {{ number_format($d->credit_limit_idr) }}</td>
                                    <td class="p-3 text-right font-medium {{ $d->isBlocked() ? 'text-red-600' : 'text-gray-700' }}">Rp {{ number_format($d->credit_exposure_idr) }}</td>
                                    <td class="p-3 text-center">
                                        <a href="{{ route('distribution.portal.home', ['distributor' => $d->id]) }}" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Buka Portal
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-gray-500">Belum ada distributor yang terdaftar dalam sistem.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
