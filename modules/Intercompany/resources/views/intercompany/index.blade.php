<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Konsolidasi Grup & Transaksi Antar-Perusahaan (Intercompany & TP)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Mirror Transactions & Elimination -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Transaksi Cermin (Mirror Transactions)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($transactions as $tx)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $tx->transaction_code }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">{{ $tx->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $tx->selling_entity }} &rarr; {{ $tx->buying_entity }}</div>
                                <div class="text-xs text-gray-400">SO: {{ $tx->sales_invoice_ref }} | PO: {{ $tx->purchase_bill_ref }}</div>
                                <div class="font-mono mt-1 font-semibold text-gray-900">IDR {{ number_format($tx->amount_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada transaksi cermin antar-entitas.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Jurnal Eliminasi Konsolidasi</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($eliminations as $elim)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $elim->elimination_code }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-800">{{ $elim->period }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Tipe: {{ $elim->elimination_type }}</div>
                                <div class="text-xs text-gray-400">DR: {{ $elim->debit_account }} / CR: {{ $elim->credit_account }}</div>
                                <div class="font-mono mt-1 font-semibold text-gray-900">IDR {{ number_format($elim->amount_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada jurnal eliminasi terposting.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Intercompany Loans & Transfer Pricing -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pinjaman Antar-Entitas (IC Loans)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($loans as $loan)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $loan->loan_agreement_number }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ $loan->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $loan->lender_entity }} &rarr; {{ $loan->borrower_entity }} | Bunga: {{ $loan->arms_length_interest_rate }}%</div>
                                <div class="font-mono mt-1 text-xs">Plafon: IDR {{ number_format($loan->principal_idr) }} | Jatuh Tempo: {{ $loan->due_date->format('Y-m-d') }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada pinjaman antar-entitas aktif.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Aturan Transfer Pricing (OECD / PMK)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($rules as $rule)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $rule->product_category }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">{{ $rule->tp_method }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Arm's Length Margin: {{ $rule->min_arms_length_margin_percent }}% &ndash; {{ $rule->max_arms_length_margin_percent }}%</div>
                                <div class="text-xs text-gray-400 mt-0.5">Benchmark: {{ $rule->benchmark_industry_source }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada aturan transfer pricing terdaftar.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
