<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Tagihan Konsolidasi Utilitas & TOU (Fase 126.3 & 126.4)') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Multi-Tariff Time-of-Use Billing, Demand Charges, Net Metering Credits & Jurnal Finansial
                </p>
            </div>
            <div>
                <a href="{{ route('egy.bills.export') }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    Unduh Rekap CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Invoice Generation Bar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Terbitkan Tagihan Konsolidasi Baru (TOU Multi-Tarif)</h3>
                <form action="{{ route('egy.bills.generate') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">ID Properti Konsumen</label>
                        <input type="text" name="property_id" value="PROP-HOTEL-01" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Periode Tagihan (YYYY-MM)</label>
                        <input type="month" name="billing_period" value="{{ now()->format('Y-m') }}" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tenggat Pembayaran (Due Date)</label>
                        <input type="date" name="due_date" value="{{ now()->addDays(20)->format('Y-m-d') }}" required
                               class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Generate & Posting Jurnal
                        </button>
                    </div>
                </form>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Daftar Tagihan Konsolidasi Utilitas</h3>
                    <span class="text-xs text-gray-500">Total: {{ $invoices->count() }} Tagihan</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Nomor Invoice</th>
                                <th class="px-4 py-3 text-left">Properti / Pelanggan</th>
                                <th class="px-4 py-3 text-left">Periode Tagihan</th>
                                <th class="px-4 py-3 text-right">Total Energi</th>
                                <th class="px-4 py-3 text-right">Total Tagihan (IDR)</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($invoices as $inv)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900 font-mono">
                                        <a href="{{ route('egy.bills.show', $inv->id) }}" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                            {{ $inv->invoice_number }}
                                        </a>
                                        <div class="text-2xs text-gray-400">Jurnal: {{ $inv->journal_entry_id ? 'POSTED' : 'PENDING' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800">
                                        <div class="font-semibold">{{ $inv->property_id }}</div>
                                        <div class="text-xs text-gray-500">{{ $inv->customer_id ?? 'Tenant Primary' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700">
                                        {{ $inv->billing_period }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        {{ number_format(($inv->total_kwh_consumption ?? 0), 2) }} kWh
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">
                                        Rp {{ number_format($inv->total_amount_idr) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($inv->status === 'draft' ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ strtoupper($inv->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center space-x-2">
                                        <a href="{{ route('egy.bills.show', $inv->id) }}" class="text-xs text-indigo-600 hover:text-indigo-900 font-semibold">
                                            Rincian
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 italic">
                                        Belum ada tagihan konsolidasi yang diterbitkan.
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
