@extends('layouts.app')

@section('title', 'Geotech Slope Stability & Quarry Drone Surveys')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Geoteknik Kestabilan Lereng & Survei Drone Kuari</h1>
            <p class="text-muted small mb-0">Pemantauan Radar InSAR Deformasi Lereng, Solver Bishop FoS, Evakuasi Bahaya Longsor & Volumetrik TIN</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <a href="{{ route('mining.fase119.export-csv', ['domain' => 'GEOTECH']) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download"></i> Ekspor CSV Geoteknik
            </a>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#evacuationModal">
                <i class="fas fa-exclamation-triangle"></i> Evakuasi Darurat KTT
            </button>
        </div>
    </div>

    <!-- Incidents & Drone Surveys Grid -->
    <div class="row g-3">
        <!-- Slope Stability Telemetry -->
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Radar Pemantauan Deformasi Lereng Tambang (InSAR / SSR)</h6>
                    <span class="badge bg-secondary">Batas Aman FoS ≥ 1.30</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Insiden / Sektor</th>
                                    <th>Tingkat Bahaya</th>
                                    <th>Laju Deformasi</th>
                                    <th>Kumulatif</th>
                                    <th>Bishop FoS</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($incidents as $inc)
                                <tr>
                                    <td class="font-weight-bold">
                                        {{ $inc->incident_code }}<br>
                                        <small class="text-muted">{{ $inc->location_sector }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $inc->severity_level === 'CRITICAL' ? 'bg-danger' : ($inc->severity_level === 'HIGH' ? 'bg-warning text-dark' : 'bg-success') }}">
                                            {{ $inc->severity_level }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="{{ $inc->displacement_rate_mm_hour >= 5.0 ? 'text-danger font-weight-bold' : '' }}">
                                            {{ number_format($inc->displacement_rate_mm_hour, 2) }} mm/h
                                        </span>
                                    </td>
                                    <td>{{ number_format($inc->cumulative_displacement_mm, 1) }} mm</td>
                                    <td>
                                        <span class="badge {{ ($inc->factor_of_safety_fos ?? 1.5) < 1.3 ? 'bg-danger' : 'bg-success' }}">
                                            {{ $inc->factor_of_safety_fos ? number_format($inc->factor_of_safety_fos, 2) : 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $inc->status === 'CRITICAL_EVACUATION' ? 'bg-danger' : ($inc->status === 'RESOLVED' ? 'bg-success' : 'bg-primary') }}">
                                            {{ $inc->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($inc->status === 'CRITICAL_EVACUATION')
                                        <form method="POST" action="{{ route('mining.fase119.geotech.resolve') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="geotech_incident_id" value="{{ $inc->id }}">
                                            <input type="hidden" name="closing_engineer_name" value="Chief Geotechnical Engineer">
                                            <input type="hidden" name="verified_stable_displacement_rate_mm_h" value="0.35">
                                            <input type="hidden" name="verified_factor_of_safety" value="1.42">
                                            <input type="hidden" name="signoff_notes" value="Remediasi counterweight kaki lereng selesai; pemantauan 48 jam stabil.">
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="Resolve Insiden">
                                                <i class="fas fa-check"></i> Selesai
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada data telemetri deformasi lereng.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quarry Drone Surveys & TIN Stockpile -->
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Survei Drone Kuari & Volumetrik Mesh TIN</h6>
                    <span class="badge bg-info">Akurasi Ortofoto ≤ 5cm</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Survei</th>
                                    <th>Pit Kuari</th>
                                    <th>Volume Bersih</th>
                                    <th>Tonase WMT</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($droneSurveys as $survey)
                                <tr>
                                    <td class="font-weight-bold">{{ $survey->survey_code }}</td>
                                    <td>{{ $survey->quarry_pit_id }}</td>
                                    <td>{{ number_format($survey->calculated_net_volume_m3 ?? 0.0, 1) }} m³</td>
                                    <td>{{ number_format($survey->calculated_tonnage_wmt ?? 0.0, 0) }} WMT</td>
                                    <td>
                                        <span class="badge {{ $survey->status === 'VOLUMETRIC_COMPLETED' ? 'bg-success' : 'bg-primary' }}">
                                            {{ $survey->status }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada survei drone yang terdaftar.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
