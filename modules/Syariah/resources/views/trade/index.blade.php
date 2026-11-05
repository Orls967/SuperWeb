@extends('layouts.app')

@section('title', 'Perdagangan Internasional & SCF Syariah')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Perdagangan Internasional & SCF Syariah</h1>
            <p class="text-sm text-gray-500 mt-1">Ekosistem pembiayaan perdagangan lintas batas berbasis akad Istisna', Salam, SCF Murabahah, dan Tawarruq Munazzam FX</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('syariah.trade.calculator') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                Kalkulator Tawarruq FX
            </a>
            <a href="{{ route('syariah.trade.custody.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                Konsol Verifikasi Qabdh
            </a>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Letter of Credit Aktif</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_lc_count']) }}</div>
            <div class="text-xs text-emerald-600 mt-1">Rp {{ number_format($stats['total_lc_volume_idr'] / 100, 2) }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Kontrak Salam Komoditas</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_salam_count']) }}</div>
            <div class="text-xs text-blue-600 mt-1">Rp {{ number_format($stats['total_salam_volume_idr'] / 100, 2) }} advance</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pagu SCF Murabahah</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_scf_facility_count']) }} Fasilitas</div>
            <div class="text-xs text-amber-600 mt-1">Rp {{ number_format($stats['total_scf_invoices_idr'] / 100, 2) }} tagihan</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Verifikasi Qabdh Logistik</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['verified_custody_count']) }} Dokumen</div>
            <div class="text-xs text-rose-500 mt-1">{{ number_format($stats['pending_qabdh_count']) }} pending verifikasi</div>
        </div>
    </div>

    <!-- Navigation Hub -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('syariah.trade.lc.index') }}" class="block p-4 bg-white border border-gray-200 rounded-lg hover:border-emerald-500 hover:shadow-md transition">
            <h3 class="font-semibold text-gray-900 text-sm">Islamic Letter of Credit (LC)</h3>
            <p class="text-xs text-gray-500 mt-1">Pembiayaan impor manufaktur istisna' bertahap dan keagenan wakalah.</p>
        </a>
        <a href="{{ route('syariah.trade.salam.index') }}" class="block p-4 bg-white border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition">
            <h3 class="font-semibold text-gray-900 text-sm">Salam & Parallel Salam</h3>
            <p class="text-xs text-gray-500 mt-1">Advance payment komoditas dan lindung nilai off-taker terstandardisasi.</p>
        </a>
        <a href="{{ route('syariah.trade.scf.index') }}" class="block p-4 bg-white border border-gray-200 rounded-lg hover:border-amber-500 hover:shadow-md transition">
            <h3 class="font-semibold text-gray-900 text-sm">Supply Chain Finance (SCF)</h3>
            <p class="text-xs text-gray-500 mt-1">Pengadaan modal kerja murabahah terverifikasi qabdh fisik/konstruktif.</p>
        </a>
        <a href="{{ route('syariah.trade.tawarruq.index') }}" class="block p-4 bg-white border border-gray-200 rounded-lg hover:border-purple-500 hover:shadow-md transition">
            <h3 class="font-semibold text-gray-900 text-sm">Commodity Murabahah FX</h3>
            <p class="text-xs text-gray-500 mt-1">Tawarruq munazzam konversi valas bebas riba dengan serial lot bursa.</p>
        </a>
    </div>

    <!-- Recent Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Letter of Credit Terbaru</h2>
                <a href="{{ route('syariah.trade.lc.index') }}" class="text-xs text-emerald-600 hover:underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3">No. LC</th>
                            <th class="px-4 py-3">Pemohon</th>
                            <th class="px-4 py-3">Nominal</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentLcs as $lc)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('syariah.trade.lc.show', $lc->id) }}" class="text-emerald-600 hover:underline">
                                    {{ $lc->lc_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $lc->applicant_party_ref }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">Rp {{ number_format($lc->amount_cents / 100, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    {{ $lc->status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada data LC</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Kontrak Salam Terbaru</h2>
                <a href="{{ route('syariah.trade.salam.index') }}" class="text-xs text-blue-600 hover:underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3">No. Kontrak</th>
                            <th class="px-4 py-3">Komoditas</th>
                            <th class="px-4 py-3">Kuantum</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentSalams as $salam)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('syariah.trade.salam.show', $salam->id) }}" class="text-blue-600 hover:underline">
                                    {{ $salam->contract_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $salam->commodity_code }} ({{ $salam->commodity_grade }})</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($salam->quantity_ordered) }} {{ $salam->quantity_unit }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $salam->status }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Belum ada kontrak salam</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
