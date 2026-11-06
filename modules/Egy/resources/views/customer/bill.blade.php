<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-base md:text-xl font-semibold leading-tight text-gray-800">
                {{ __('Tagihan: ') }} {{ $invoice->invoice_number }}
            </h2>
            <a href="{{ route('egy.customer.index') }}" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">
                &larr; Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-4 md:py-6">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 space-y-4">
            <!-- Mobile Optimized Invoice Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <div class="border-b border-gray-100 pb-4 flex justify-between items-start">
                    <div>
                        <div class="text-2xs uppercase tracking-wider text-gray-400">Pemberitahuan Tagihan Utilitas</div>
                        <h3 class="text-lg font-bold text-gray-900 mt-0.5">{{ $invoice->invoice_number }}</h3>
                        <p class="text-xs text-gray-500">Periode: {{ $invoice->billing_period }} | Jatuh Tempo: {{ $invoice->due_date }}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                        {{ strtoupper($invoice->status) }}
                    </span>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Pelanggan</span>
                        <span class="font-semibold text-gray-800">{{ $invoice->customer_id ?? 'Tenant User' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Properti</span>
                        <span class="font-semibold text-gray-800">{{ $invoice->property_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Konsumsi</span>
                        <span class="font-semibold text-gray-800 font-mono">{{ number_format($invoice->total_kwh_consumption ?? 0, 2) }} kWh</span>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500">Rincian Komponen</h4>
                    @foreach($invoice->lines as $line)
                        <div class="flex justify-between text-xs py-1.5 border-b border-gray-100">
                            <div>
                                <div class="font-medium text-gray-800">{{ $line->description }}</div>
                                <div class="text-2xs text-gray-400 font-mono">{{ number_format($line->quantity, 2) }} {{ $line->unit }} @ Rp {{ number_format($line->unit_rate_idr) }}</div>
                            </div>
                            <div class="font-bold font-mono {{ $line->total_amount_idr < 0 ? 'text-emerald-700' : 'text-gray-900' }}">
                                Rp {{ number_format($line->total_amount_idr) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t-2 border-gray-100 flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-800">Total Tagihan</span>
                    <span class="text-xl font-bold font-mono text-indigo-700">Rp {{ number_format($invoice->total_amount_idr) }}</span>
                </div>

                @if($invoice->status !== 'paid')
                    <div class="pt-4">
                        <button type="button" class="w-full inline-flex justify-center items-center px-4 py-3 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 active:bg-emerald-800 shadow-md transition">
                            Bayar Sekarang via Dompet Digital / Virtual Account
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
