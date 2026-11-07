@extends('layouts.app')

@section('title', 'Controlled Drill & Blasting & KCO Fragmentation Analysis')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Pusat Kendali Peledakan & Analisis Fragmentasi Muckpile (KCO)</h1>
            <p class="text-muted small mb-0">Verifikasi Geofence Personel, Interlock Sirine KTT, Detonasi Terkendali & Koreksi Pola Pengeboran</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <a href="{{ route('mining.fase119.export-csv', ['domain' => 'BLASTING']) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download"></i> Ekspor Log Peledakan
            </a>
            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#newBlastModal">
                <i class="fas fa-bomb"></i> Buat Rencana Peledakan
            </button>
        </div>
    </div>

    <!-- Active Runs Table Responsive -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Daftar Siklus Peledakan (Blast Runs)</h6>
                    <span class="badge bg-secondary">Batas Eksklusi Min 500m</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Run</th>
                                    <th>Pola & Pit</th>
                                    <th>Lubang & Handak</th>
                                    <th>Radius Aman</th>
                                    <th>Sterilisasi</th>
                                    <th>Status</th>
                                    <th>Aksi Kendali</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($blastRuns as $run)
                                <tr>
                                    <td class="font-weight-bold">{{ $run->run_code }}</td>
                                    <td>{{ $run->pattern_id }} <br><small class="text-muted">{{ $run->pit_name }}</small></td>
                                    <td>{{ $run->planned_holes_count }} lubang <br><small class="text-muted">{{ number_format($run->total_explosives_kg, 0) }} kg ANFO</small></td>
                                    <td>{{ $run->safety_radius_meters }} meter</td>
                                    <td>
                                        @if($run->all_personnel_evacuated)
                                            <span class="badge bg-success"><i class="fas fa-shield-alt"></i> STERIL</span>
                                        @else
                                            <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> PERSONEL AKTIF</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $run->status === 'DETONATED' ? 'bg-dark text-white' : ($run->status === 'SIREN_ACTIVATED' ? 'bg-warning text-dark' : ($run->status === 'PERIMETER_CLEARED' ? 'bg-info' : 'bg-secondary')) }}">
                                            {{ $run->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            @if($run->status === 'DRAFT' || $run->status === 'PERIMETER_CLEARED')
                                            <form method="POST" action="{{ route('mining.fase119.blast.verify') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="blast_clearance_run_id" value="{{ $run->id }}">
                                                <button type="submit" class="btn btn-outline-info btn-sm" title="Scan Radar Perimeter">
                                                    <i class="fas fa-radar"></i> Scan
                                                </button>
                                            </form>
                                            @endif

                                            @if($run->all_personnel_evacuated && $run->status === 'PERIMETER_CLEARED')
                                            <form method="POST" action="{{ route('mining.fase119.blast.siren') }}" class="d-inline ms-1">
                                                @csrf
                                                <input type="hidden" name="blast_clearance_run_id" value="{{ $run->id }}">
                                                <input type="hidden" name="siren_stage" value="STAGE_3_FINAL_1MIN">
                                                <input type="hidden" name="blaster_in_charge_kjl" value="KJL-ESDM-9941">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Bunyikan Sirine">
                                                    <i class="fas fa-bullhorn"></i> Sirine
                                                </button>
                                            </form>
                                            @endif

                                            @if($run->status === 'SIREN_ACTIVATED')
                                            <form method="POST" action="{{ route('mining.fase119.blast.detonate') }}" class="d-inline ms-1">
                                                @csrf
                                                <input type="hidden" name="blast_clearance_run_id" value="{{ $run->id }}">
                                                <input type="hidden" name="initiated_by_user_id" value="KTT-OPERATOR-1">
                                                <input type="hidden" name="detonator_box_serial" value="EXPLODER-DIGITAL-X90">
                                                <button type="submit" class="btn btn-danger btn-sm font-weight-bold" title="Tembak Detonasi">
                                                    <i class="fas fa-fire"></i> TEMBAK
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada siklus peledakan yang terdaftar.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- KCO Fragmentation Curve Summary Card -->
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-dark">Model Fragmentasi Batuan KCO (Kuz-Ram)</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Distribusi ukuran bongkah muckpile batuan pasca-detonasi dan kepatuhan terhadap crusher grizzly aperture (max 800 mm).</p>

                    @forelse($evaluations as $eval)
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="font-weight-bold small">{{ $eval->eval_code }}</span>
                            <span class="badge {{ $eval->status === 'OPTIMAL' ? 'bg-success' : 'bg-danger' }}">{{ $eval->status }}</span>
                        </div>
                        <div class="row text-center g-1 mb-1 small">
                            <div class="col-4 border-end">
                                <span class="text-muted d-block">d₅₀ (mm)</span>
                                <strong>{{ $eval->mean_fragment_size_d50_mm }}</strong>
                            </div>
                            <div class="col-4 border-end">
                                <span class="text-muted d-block">d₈₀ (mm)</span>
                                <strong>{{ $eval->passing_80_percent_d80_mm }}</strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted d-block">Boulders</span>
                                <strong class="{{ $eval->boulder_oversize_percent > 15 ? 'text-danger' : 'text-success' }}">
                                    {{ $eval->boulder_oversize_percent }}%
                                </strong>
                            </div>
                        </div>
                        <div class="small text-muted">Powder Factor: <strong>{{ $eval->powder_factor_kg_m3 }} kg/m³</strong></div>
                    </div>
                    @empty
                    <p class="text-center text-muted small py-3">Belum ada evaluasi fragmentasi muckpile.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
