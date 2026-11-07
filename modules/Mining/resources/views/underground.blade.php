@extends('layouts.app')

@section('title', 'Underground Digital Twin & Hardy-Cross Ventilation')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Breadcrumb & Header Responsive -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Underground Digital Twin & Hardy-Cross Ventilation</h1>
            <p class="text-muted small mb-0">Model 3D Stope Tambang Bawah Tanah, Solver Sirkulasi Aerodinamika & Interlock Gas Kritis</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <a href="{{ route('mining.fase119.export-csv', ['domain' => 'UNDERGROUND']) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download"></i> Ekspor CSV Audit
            </a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newStopeModal">
                <i class="fas fa-plus"></i> Tambah Blok Stope
            </button>
        </div>
    </div>

    <!-- KPI Cards Responsive (Grid 1 col on 375px mobile, 4 col on desktop) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-primary shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Stope Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($activeStopes) }} Stope</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cube fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-success shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Intake Udara Bersih</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $ventSims->first() ? $ventSims->first()->intake_airflow_m3_s : '142.5' }} m³/s
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wind fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-warning shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Status Gas Metana (CH₄)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">0.22% <span class="badge bg-success">AMAN</span></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-smog fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-info shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Kadar Oksigen (O₂) Rata-rata</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">20.8%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-lungs fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Stopes & Hardy-Cross Loop Grid -->
    <div class="row g-3">
        <!-- Stope Table Responsive -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Daftar Blok Stope Bawah Tanah & RMR Geomekanik</h6>
                    <span class="badge bg-info">Level -250m s/d -500m RL</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Stope</th>
                                    <th>Level</th>
                                    <th>Metode Tambang</th>
                                    <th>RMR</th>
                                    <th>Kadar Ore</th>
                                    <th>Tonase Terencana</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activeStopes as $stope)
                                <tr>
                                    <td class="font-weight-bold">{{ $stope->stope_code }}</td>
                                    <td>{{ $stope->level_name }}</td>
                                    <td><span class="badge bg-secondary">{{ $stope->mining_method }}</span></td>
                                    <td>
                                        <span class="badge {{ $stope->rock_mass_rating >= 60 ? 'bg-success' : ($stope->rock_mass_rating >= 40 ? 'bg-warning' : 'bg-danger') }}">
                                            RMR {{ $stope->rock_mass_rating }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($stope->ore_grade, 2) }}%</td>
                                    <td>{{ number_format($stope->planned_tonnage, 0) }} WMT</td>
                                    <td>
                                        <span class="badge {{ $stope->status === 'OPERATIONAL' ? 'bg-success' : ($stope->status === 'DEVELOPMENT' ? 'bg-primary' : 'bg-warning text-dark') }}">
                                            {{ $stope->status }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada blok stope yang terdaftar.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hardy-Cross Solver Quick Run Card -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-dark">Hardy-Cross Ventilation Loop Control</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Simulasi solver kesetimbangan hukum Kirchhoff II ($\sum \Delta h = 0$) pada jaringan shaft intake, return, dan stope.</p>

                    <form id="hardyCrossForm" method="POST" action="{{ route('mining.fase119.ventilation.simulate') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small font-weight-bold">Kode Simulasi</label>
                            <input type="text" name="simulation_code" class="form-control form-control-sm" value="SIM-VENT-{{ date('Ymd-Hi') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small font-weight-bold">Fan Speed (VSD %)</label>
                            <input type="number" name="fan_rpm_percent" class="form-control form-control-sm" value="100" min="40" max="120" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small font-weight-bold">Headcount Bawah Tanah</label>
                            <input type="number" name="headcount_underground" class="form-control form-control-sm" value="30" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small font-weight-bold">Armada Diesel Aktif (kW)</label>
                            <input type="number" name="diesel_fleet_kw" class="form-control form-control-sm" value="450" min="0" required>
                        </div>

                        <!-- Hidden preset loop payload for direct solver test -->
                        <input type="hidden" name="branches[0][id]" value="BR-INTAKE-1">
                        <input type="hidden" name="branches[0][name]" value="Main Intake Shaft">
                        <input type="hidden" name="branches[0][resistance_r]" value="0.045">
                        <input type="hidden" name="branches[0][initial_flow_q]" value="120.0">
                        <input type="hidden" name="branches[0][fan_pressure_pf]" value="1200.0">

                        <input type="hidden" name="branches[1][id]" value="BR-STOPE-NORTH">
                        <input type="hidden" name="branches[1][name]" value="North Stope Working Drift">
                        <input type="hidden" name="branches[1][resistance_r]" value="0.120">
                        <input type="hidden" name="branches[1][initial_flow_q]" value="60.0">

                        <input type="hidden" name="branches[2][id]" value="BR-RETURN-1">
                        <input type="hidden" name="branches[2][name]" value="Exhaust Ventilation Shaft">
                        <input type="hidden" name="branches[2][resistance_r]" value="0.055">
                        <input type="hidden" name="branches[2][initial_flow_q]" value="120.0">

                        <input type="hidden" name="loops[0][loop_id]" value="LOOP-MAIN-01">
                        <input type="hidden" name="loops[0][branch_ids][]" value="BR-INTAKE-1">
                        <input type="hidden" name="loops[0][branch_ids][]" value="BR-STOPE-NORTH">
                        <input type="hidden" name="loops[0][branch_ids][]" value="BR-RETURN-1">
                        <input type="hidden" name="loops[0][directions][]" value="1">
                        <input type="hidden" name="loops[0][directions][]" value="1">
                        <input type="hidden" name="loops[0][directions][]" value="-1">

                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-calculator me-1"></i> Jalankan Solver Hardy-Cross
                        </button>
                    </form>

                    @if($ventSims->isNotEmpty())
                    <hr>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Simulasi Terakhir:</span>
                            <span class="font-weight-bold">{{ $ventSims->first()->simulation_code }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Iterasi Konvergensi:</span>
                            <span class="badge bg-success">{{ $ventSims->first()->iterations_to_converge }} iterasi</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status Validitas:</span>
                            <span class="badge bg-primary">{{ $ventSims->first()->status }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
