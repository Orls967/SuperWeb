<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Treasury & Multi-Currency Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Master Mata Uang & Kurs -->
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Mata Uang & Kurs Spot</h3>
                    <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">Multi-Currency Engine</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-2">Mata Uang Aktif</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Simbol</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Minor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($currencies as $c)
                                        <tr>
                                            <td class="px-4 py-2 font-bold">{{ $c->code }}</td>
                                            <td class="px-4 py-2">{{ $c->name }}</td>
                                            <td class="px-4 py-2">{{ $c->symbol }}</td>
                                            <td class="px-4 py-2">{{ $c->minor_units }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-2 text-sm text-gray-500">Belum ada mata uang tercatat.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-2">Nilai Tukar Terbaru</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pasangan</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kurs (Scaled)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($rates as $r)
                                        <tr>
                                            <td class="px-4 py-2 font-bold">{{ $r->from_currency }}/{{ $r->to_currency }}</td>
                                            <td class="px-4 py-2">{{ $r->rate_date->format('Y-m-d') }}</td>
                                            <td class="px-4 py-2">{{ ucfirst($r->rate_type) }}</td>
                                            <td class="px-4 py-2 font-mono">{{ number_format($r->rate_numerator / $r->rate_denominator, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-2 text-sm text-gray-500">Belum ada kurs tercatat.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rekening Kas & Fasilitas Kredit -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Rekening Bank & Kas</h3>
                    <div class="space-y-3">
                        @forelse($accounts as $acc)
                            <div class="border rounded p-3 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-gray-800">{{ $acc->bank_name }} - {{ $acc->account_number }}</div>
                                    <div class="text-xs text-gray-500">{{ $acc->is_petty_cash ? 'Kas Kecil' : 'Rekening Operasional' }} ({{ $acc->currency }})</div>
                                </div>
                                <div class="font-mono font-semibold text-blue-600">
                                    {{ $acc->currency }} {{ number_format($acc->balance) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada rekening terdaftar.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Fasilitas Kredit & Covenant Bank</h3>
                    <div class="space-y-3">
                        @forelse($facilities as $fac)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <div class="font-bold text-gray-800">{{ $fac->facility_code }} ({{ $fac->bank_name }})</div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-800">{{ $fac->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Bunga: {{ $fac->interest_rate_percent }}% | Max DER: {{ $fac->max_debt_equity_ratio }}x
                                </div>
                                <div class="mt-2 text-sm">
                                    Plafon: IDR {{ number_format($fac->credit_limit_idr) }} | Terpakai: IDR {{ number_format($fac->drawn_amount_idr) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada fasilitas kredit tercatat.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
