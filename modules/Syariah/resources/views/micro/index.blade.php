@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Pembiayaan Mikro Syariah (Fase 163)</h1>
            <p class="text-sm text-slate-600 mt-1">Pembiayaan kelompok tanggung renteng mudharabah bertingkat, kredit mikro gig worker (G7-K6), dan salam pertanian terikat NDVI (G7-K1/G7-K2).</p>
        </div>
    </div>

    <!-- Tab Section 1: Kelompok Tanggung Renteng -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Pembiayaan Kelompok Tanggung Renteng (Mudharabah Bertingkat)</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Kelompok</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Nama Kelompok</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Anggota</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Total Plafon</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Sisa Pokok</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Tanggung Renteng</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($groupFinancings as $grp)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $grp->group_code }}</td>
                            <td class="px-4 py-2 text-slate-900 font-medium">{{ $grp->group_name }}</td>
                            <td class="px-4 py-2 text-center font-mono">{{ $grp->total_members }} orang</td>
                            <td class="px-4 py-2 text-right font-mono">Rp {{ number_format($grp->total_facility_amount_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-emerald-700">Rp {{ number_format($grp->outstanding_balance_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Aktif</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('syariah.micro.group.show', $grp->id) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-slate-500">Belum ada pembiayaan kelompok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab Section 2: Gig Worker Microcredit -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Pembiayaan Mikro Gig Worker (G7-K6 & Algoritma B6)</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Pinjaman</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Pekerja (Party)</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Skor Amanah</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Limit Bersertifikat</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Sisa Saldo</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Potongan Bulanan</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($gigLoans as $gig)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $gig->loan_code }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $gig->worker_party_ref }}</td>
                            <td class="px-4 py-2 text-center font-mono font-bold text-emerald-600">{{ $gig->amanah_score }}/100</td>
                            <td class="px-4 py-2 text-right font-mono">Rp {{ number_format($gig->approved_credit_limit_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-slate-900">Rp {{ number_format($gig->remaining_balance_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-mono text-emerald-700">Rp {{ number_format($gig->monthly_deduction_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('syariah.micro.gig.show', $gig->id) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-slate-500">Belum ada pembiayaan gig worker.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab Section 3: Agri Salam Microfinance -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Pembiayaan Salam Pertanian (Terikat NDVI Satelit G7-K1)</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Fasilitas</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Petani</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Petak / Komoditas</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Nilai Kontrak</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Indeks NDVI</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($agriFacilities as $agri)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $agri->facility_code }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $agri->farmer_party_ref }}</td>
                            <td class="px-4 py-2 text-slate-900 font-medium">{{ $agri->plot_code }} ({{ $agri->commodity }})</td>
                            <td class="px-4 py-2 text-right font-mono">Rp {{ number_format($agri->financing_amount_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center font-mono font-medium {{ $agri->current_ndvi_bps >= $agri->ndvi_min_threshold_bps ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ number_format($agri->current_ndvi_bps / 10000, 2) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">{{ $agri->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('syariah.micro.agri.show', $agri->id) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-slate-500">Belum ada pembiayaan salam pertanian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
