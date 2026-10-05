<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Trade Finance & Banking Instruments (L/C, Garansi, Koleksi)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Letters of Credit -->
            <div class="p-6 bg-white shadow sm:rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Letter of Credit (UCP 600 Simulasi)</h3>
                    <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">Trade Exposure</span>
                </div>
                <div class="space-y-3">
                    @forelse($lcs as $lc)
                        <div class="border rounded p-3 flex justify-between items-center text-sm">
                            <div>
                                <span class="font-bold text-gray-800">{{ $lc->lc_number }}</span> ({{ ucfirst($lc->type) }})
                                <div class="text-xs text-gray-500">Applicant: {{ $lc->applicant_name }} &rarr; Beneficiary: {{ $lc->beneficiary_name }}</div>
                                <div class="text-xs text-gray-400">Bank: {{ $lc->issuing_bank }} | Exp: {{ $lc->expiry_date->format('Y-m-d') }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-blue-600 font-mono">{{ $lc->currency }} {{ number_format($lc->amount_foreign) }}</div>
                                <span class="px-2 py-0.5 text-xs rounded font-semibold bg-gray-100">{{ $lc->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada L/C diterbitkan.</p>
                    @endforelse
                </div>
            </div>

            <!-- Garansi Bank & Pembiayaan -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Garansi Bank (Bank Guarantee)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($guarantees as $bg)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $bg->guarantee_number }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">{{ $bg->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Jenis: {{ $bg->type }} | Bank: {{ $bg->issuing_bank }}</div>
                                <div class="font-mono mt-1 font-semibold text-gray-900">IDR {{ number_format($bg->amount_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada garansi bank.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pinjaman Perdagangan & SCF</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($loans as $loan)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $loan->loan_number }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-800">{{ $loan->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Fasilitas: {{ $loan->facility_type }} | Bunga: {{ $loan->interest_rate_percent }}%</div>
                                <div class="font-mono mt-1">
                                    Plafon: IDR {{ number_format($loan->principal_amount_idr) }} | Terbayar: IDR {{ number_format($loan->repaid_amount_idr) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada fasilitas pembiayaan aktif.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
