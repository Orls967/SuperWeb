<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Treasury Cash & Liquidity Positions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow rounded-lg">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Kas IDR</h3>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900">Rp {{ number_format($totalIdr ?? 0) }}</p>
                    <span class="inline-flex mt-2 items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Likuiditas Stabil</span>
                </div>
                <div class="p-6 bg-white shadow rounded-lg">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Kas USD</h3>
                    <p class="mt-2 text-3xl font-extrabold text-blue-600">$ {{ number_format(($totalUsd ?? 0) / 100, 2) }}</p>
                    <span class="inline-flex mt-2 items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Cadangan Valas</span>
                </div>
            </div>

            <div class="p-6 bg-white shadow rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daftar Rekening Bank & Kas Kecil</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rekening</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mata Uang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($accounts as $acc)
                                <tr>
                                    <td class="px-6 py-4 font-mono text-sm font-bold">{{ $acc->account_number }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $acc->bank_name }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $acc->currency }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($acc->is_petty_cash)
                                            <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">Kas Kecil</span>
                                        @else
                                            <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">Operasional</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold">
                                        {{ number_format($acc->balance) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada rekening kas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
