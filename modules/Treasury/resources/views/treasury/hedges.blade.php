<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('FX Hedging & Derivative Contracts') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Kontrak Lindung Nilai Forward (8-Status Lifecycle)</h3>
                    <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">Four-Eyes Maker-Checker</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No. Kontrak</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valas</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nosional</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">MTM IDR</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($contracts as $c)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs font-bold">{{ $c->contract_number }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $c->currency }}</td>
                                    <td class="px-4 py-2 text-right text-sm">{{ number_format($c->notional_foreign_amount) }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $c->maturity_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold {{ $c->mtm_value_idr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($c->mtm_value_idr) }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-xs">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 font-medium">
                                            {{ $c->status instanceof \Modules\Treasury\Domain\Enums\HedgeStatus ? $c->status->value : $c->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada kontrak lindung nilai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

