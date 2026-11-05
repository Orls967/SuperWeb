@extends('layouts.app')

@section('title', 'Detail LC ' . $lc->lc_number)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.lc.index') }}" class="hover:underline">Letter of Credit</a> / Detail</div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $lc->lc_number }}</h1>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 self-start sm:self-auto">
            Status: {{ $lc->status }}
        </span>
    </div>

    <!-- LC Summary Card -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Informasi Kontrak LC & Akad Wakalah</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div>
                <span class="text-gray-500">Pemohon (Importer):</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $lc->applicant_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Penerima (Beneficiary):</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $lc->beneficiary_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Bank Penerbit:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $lc->issuing_bank_party_ref }}</p>
            </div>
            <div>
                <span class="text-gray-500">Nilai Kontrak:</span>
                <p class="font-semibold text-gray-900 text-sm mt-0.5">{{ $lc->amount_currency }} {{ number_format($lc->amount_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Setoran Margin Jaminan:</span>
                <p class="font-medium text-gray-900 mt-0.5">Rp {{ number_format($lc->margin_deposit_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Ujrah Keagenan Wakalah:</span>
                <p class="font-medium text-gray-900 mt-0.5">Rp {{ number_format($lc->wakalah_fee_cents / 100, 2) }}</p>
            </div>
            <div>
                <span class="text-gray-500">Tanggal Jatuh Tempo:</span>
                <p class="font-medium text-gray-900 mt-0.5">{{ $lc->expiry_date->format('d M Y') }}</p>
            </div>
        </div>
        @if($lc->terms_conditions)
        <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-600">
            <span class="font-medium text-gray-700">Syarat & Ketentuan Akad:</span> {{ $lc->terms_conditions }}
        </div>
        @endif
    </div>

    <!-- Istisna Orders Section -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Pesanan Manufaktur Istisna' & Progress Cost-to-Cost</h2>
        </div>
        <div class="p-6 space-y-6">
            @forelse($lc->istisnaContracts as $istisna)
            <div class="border border-gray-200 rounded-lg p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <div>
                        <span class="text-xs text-gray-500">No. Kontrak Istisna':</span>
                        <h3 class="font-bold text-gray-900">{{ $istisna->contract_number }}</h3>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-gray-500">Progress POC:</span>
                        <div class="font-bold text-emerald-600 text-base">{{ $istisna->progress_percent }}%</div>
                    </div>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min(100, $istisna->progress_percent) }}%"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs mb-4">
                    <div>
                        <span class="text-gray-500">Produsen/Manufaktur:</span>
                        <p class="font-medium text-gray-900">{{ $istisna->manufacturer_party_ref }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Harga Kontrak:</span>
                        <p class="font-medium text-gray-900">Rp {{ number_format($istisna->total_price_cents / 100, 2) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Anggaran Biaya:</span>
                        <p class="font-medium text-gray-900">Rp {{ number_format($istisna->budgeted_cost_cents / 100, 2) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Biaya Aktual / Diakui:</span>
                        <p class="font-medium text-gray-900">Rp {{ number_format($istisna->actual_cost_incurred_cents / 100, 2) }} / Rp {{ number_format($istisna->recognized_revenue_cents / 100, 2) }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-600 mb-4 bg-gray-50 p-3 rounded border border-gray-100">
                    <span class="font-medium text-gray-700">Spesifikasi Teknis:</span> {{ $istisna->specification_summary }}
                </p>

                <!-- Milestones Table -->
                @if($istisna->milestones->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Milestone</th>
                                <th class="px-3 py-2">Biaya Dicatat</th>
                                <th class="px-3 py-2">Persentase</th>
                                <th class="px-3 py-2">Catatan Inspeksi</th>
                                <th class="px-3 py-2">Verifikator</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($istisna->milestones as $ms)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $ms->milestone_name }}</td>
                                <td class="px-3 py-2 text-gray-700">Rp {{ number_format($ms->cost_incurred_cents / 100, 2) }}</td>
                                <td class="px-3 py-2 font-semibold text-emerald-600">{{ $ms->progress_percentage }}%</td>
                                <td class="px-3 py-2 text-gray-600">{{ $ms->inspection_note ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $ms->verifier_ref ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @empty
            <p class="text-xs text-gray-400 text-center py-4">Belum ada pesanan manufaktur istisna' pada LC ini.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
