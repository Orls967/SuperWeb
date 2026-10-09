<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Finance Grup, Anggaran, Pajak & Tata Kelola (SoD)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Enterprise Budgets & Tax Summaries -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pengendalian Anggaran (Enterprise Budgeting)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($budgets as $bgt)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $bgt->budget_code }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-800">{{ $bgt->control_type }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Cost Center: {{ $bgt->cost_center_code }} | Akun: {{ $bgt->account_code }}</div>
                                <div class="font-mono mt-1 text-xs">
                                    Pagu: IDR {{ number_format($bgt->allocated_amount_idr) }} | Komitmen: IDR {{ number_format($bgt->encumbered_amount_idr) }} | Realisasi: IDR {{ number_format($bgt->spent_amount_idr) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada alokasi anggaran aktif.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Rekonsiliasi Pajak Nasional (PPN & PPh)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($taxes as $tax)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $tax->tax_type }} ({{ $tax->period }})</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ $tax->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">DPP: IDR {{ number_format($tax->tax_base_idr) }}</div>
                                <div class="font-mono mt-1 text-xs">Kurang Bayar / Disetor: IDR {{ number_format($tax->payable_or_refundable_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada ringkasan pelaporan pajak.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- SoD Matrix & Compliance Deadlines -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Matriks Pemisahan Tugas (SoD Engine)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($sodRules as $rule)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $rule->rule_code }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-800">{{ $rule->risk_level }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Konflik: {{ $rule->role_a }} &harr; {{ $rule->role_b }}</div>
                                <div class="text-xs text-gray-600 mt-1">{{ $rule->description }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada aturan SoD terdaftar.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Kalender Kepatuhan Regulasi</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($deadlines as $dl)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $dl->title }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">{{ $dl->regulatory_body }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Jatuh Tempo: {{ $dl->due_date->format('Y-m-d') }} | PIC: {{ $dl->assigned_role }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Semua kewajiban kepatuhan telah terpenuhi.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
