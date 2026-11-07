@extends('layouts.app')

@section('title', 'Marine Barge Hydrostatic Draft Survey & Logistics')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Pengapalan Tongkang Laut & Rekonsiliasi Draft Survey (M9)</h1>
            <p class="text-muted small mb-0">Pengukuran Draf 6-Titik Kapal, Interpolasi Tabel Hidrostatik, Audit Rantai Pasok Syahbandar & Settlement Ongkos Angkut</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <a href="{{ route('mining.fase119.export-csv', ['domain' => 'BARGE_MARINE']) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download"></i> Ekspor CSV Marine
            </a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newDraftSurveyModal">
                <i class="fas fa-ship"></i> Catat Draft Survey
            </button>
        </div>
    </div>

    <!-- Active Barge Surveys Table Responsive -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-dark">Daftar Pengapalan Tongkang & Draft Survey 6-Titik</h6>
            <span class="badge bg-secondary">Toleransi Draft vs Timbangan Jetty ≤ 1.5%</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Tongkang</th>
                            <th>Tipe Survei</th>
                            <th>Draf Haluan (FP/FS)</th>
                            <th>Draf Tengah (MP/MS)</th>
                            <th>Draf Buritan (AP/AS)</th>
                            <th>Muatan Bersih WMT</th>
                            <th>Status Pengapalan</th>
                            <th>Surveyor Terdaftar</th>
                            <th>Aksi Operasional</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($surveys as $survey)
                        <tr>
                            <td class="font-weight-bold">{{ $survey->barge_code }}</td>
                            <td>
                                <span class="badge {{ $survey->survey_type === 'INITIAL_LIGHT' ? 'bg-secondary' : 'bg-primary' }}">
                                    {{ $survey->survey_type }}
                                </span>
                            </td>
                            <td>{{ number_format($survey->draft_forward_port, 2) }}m / {{ number_format($survey->draft_forward_starboard, 2) }}m</td>
                            <td>{{ number_format($survey->draft_midship_port, 2) }}m / {{ number_format($survey->draft_midship_starboard, 2) }}m</td>
                            <td>{{ number_format($survey->draft_aft_port, 2) }}m / {{ number_format($survey->draft_aft_starboard, 2) }}m</td>
                            <td>
                                @if($survey->cargo_tonnage_wmt)
                                    <strong class="text-success">{{ number_format($survey->cargo_tonnage_wmt, 1) }} WMT</strong>
                                @else
                                    <span class="text-muted">Dalam Proses</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $survey->status === 'DELIVERED_AND_SETTLED' ? 'bg-success' : ($survey->status === 'DEPARTED' ? 'bg-info' : 'bg-warning text-dark') }}">
                                    {{ $survey->status }}
                                </span>
                            </td>
                            <td>{{ $survey->surveyor_name }}<br><small class="text-muted">{{ $survey->surveyor_company }}</small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($survey->status === 'FINAL_SURVEY_COMPLETED' || $survey->status === 'RECONCILED')
                                    <form method="POST" action="{{ route('mining.fase119.barge.chain') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="barge_draft_survey_id" value="{{ $survey->id }}">
                                        <input type="hidden" name="jetty_id" value="JETTY-SPECIAL-01">
                                        <input type="hidden" name="syahbandar_spb_number" value="SPB-PORT-{{ date('Ymd') }}-991">
                                        <input type="hidden" name="certificate_of_analysis_number" value="COA-GEO-SURV-1182">
                                        <input type="hidden" name="tamper_seal_numbers[]" value="SEAL-TAMPER-A101">
                                        <input type="hidden" name="tamper_seal_numbers[]" value="SEAL-TAMPER-A102">
                                        <input type="hidden" name="auditor_name" value="Independent Port Marine Surveyor">
                                        <input type="hidden" name="destination_port" value="Smelter Industrial Port Morowali">
                                        <button type="submit" class="btn btn-outline-info btn-sm" title="Audit SPB & Segel Tongkang">
                                            <i class="fas fa-stamp"></i> SPB
                                        </button>
                                    </form>
                                    @endif

                                    @if($survey->status === 'DEPARTED')
                                    <form method="POST" action="{{ route('mining.fase119.barge.freight') }}" class="d-inline ms-1">
                                        @csrf
                                        <input type="hidden" name="barge_draft_survey_id" value="{{ $survey->id }}">
                                        <input type="hidden" name="contract_freight_rate_per_ton_idr" value="145000">
                                        <input type="hidden" name="allowed_laytime_hours" value="72">
                                        <input type="hidden" name="actual_laytime_hours" value="68">
                                        <input type="hidden" name="demurrage_rate_per_day_idr" value="50000000">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Pelunasan Freight Ledger">
                                            <i class="fas fa-file-invoice-dollar"></i> Settle
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Belum ada survei draf tongkang yang tercatat.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
