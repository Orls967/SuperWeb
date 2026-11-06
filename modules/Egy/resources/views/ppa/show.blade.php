<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Kontrak PPA: ') }} {{ $contract->contract_number }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    IPP: {{ $contract->seller_legal_name }} | Kapasitas: {{ number_format($contract->contracted_capacity_kw) }} kW | Status: {{ strtoupper($contract->status) }}
                </p>
            </div>
            <div>
                <a href="{{ route('egy.ppa.list') }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    Kembali ke Portofolio
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Contract Detail Overview -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Tarif Kapasitas / Availability</div>
                        <div class="text-xl font-bold font-mono text-gray-900 mt-1">
                            Rp {{ number_format($contract->capacity_charge_idr_per_kw_month) }}/kW/bln
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Tarif Energi Take-or-Pay</div>
                        <div class="text-xl font-bold font-mono text-indigo-700 mt-1">
                            Rp {{ number_format($contract->energy_rate_idr_per_kwh) }}/kWh
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Target Minimum Availability</div>
                        <div class="text-xl font-bold font-mono text-emerald-700 mt-1">
                            {{ number_format($contract->target_availability_pct ?? 95.0, 1) }}%
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Akun Hutang Produsen (AP)</div>
                        <div class="text-sm font-mono text-gray-800 mt-1">
                            {{ $contract->vendor_account_code ?? '2110-EGY-IPP' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settlement History Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Riwayat Settlement Finansial Kontrak</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Periode</th>
                                <th class="px-4 py-3 text-right">Pembayaran Kapasitas (IDR)</th>
                                <th class="px-4 py-3 text-right">Pembayaran Energi (IDR)</th>
                                <th class="px-4 py-3 text-right">Penalti Outage (IDR)</th>
                                <th class="px-4 py-3 text-right">Total Tagihan IPP (IDR)</th>
                                <th class="px-4 py-3 text-center">Status Jurnal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($contract->settlements as $s)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono font-bold text-gray-900">
                                        {{ $s->settlement_period }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-800">
                                        Rp {{ number_format($s->capacity_payment_idr) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-800">
                                        Rp {{ number_format($s->energy_payment_idr) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-rose-600">
                                        Rp {{ number_format($s->penalty_amount_idr ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                        Rp {{ number_format($s->total_payable_idr) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono {{ $s->journal_entry_id ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $s->journal_entry_id ? 'POSTED' : 'DRAFT' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400 italic">
                                        Belum ada data penyelesaian (settlement) untuk kontrak ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
