@extends('layouts.app')

@section('title', 'Smelter Pyrometallurgy & Energy Balance')

@section('content')
<div class="container-fluid px-2 px-md-4 py-3">
    <!-- Header Responsive (375px mobile friendly) -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h3 text-gray-800 mb-1">Smelter RKEF Pyrometallurgy & Energy Balance</h1>
            <p class="text-muted small mb-0">Pengendalian Tanur Peleburan Rotary Kiln Electric Arc (RKEF), Neraca Termodinamika & Emisi GRK Scope 1/2</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <a href="{{ route('mining.fase121.export-csv') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download"></i> Ekspor CSV
            </a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newFurnaceRunModal">
                <i class="fas fa-plus"></i> Rencana Run Tanur
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards (Grid 1 col on mobile 375px, 4 col on desktop) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-danger shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Tanur Peleburan Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($furnaces) }} Unit RKEF</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-fire fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-left-primary shadow-sm h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Batch Peleburan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($furnaceRuns) }} Siklus</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sync fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Suhu Rata-Rata Tapping</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $furnaceRuns->first() ? number_format((float) $furnaceRuns->first()->tapping_temperature_c, 1) : '1.520,0' }} °C
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-temperature-high fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Rata-Rata Recovery Logam</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $furnaceRuns->first() ? number_format((float) $furnaceRuns->first()->metal_recovery_rate_pct, 1) : '92,5' }} %
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Furnace Runs Table (Mobile responsive) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Siklus Batch Tanur Peleburan Smelter</h6>
            <span class="badge bg-secondary">Real-Time SCADA Sync</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Batch</th>
                            <th>Tanur</th>
                            <th>Umpan Bijih (WMT / DMT)</th>
                            <th>Kadar Umpan (% Ni / Fe)</th>
                            <th>Konsumsi Daya (MWh)</th>
                            <th>Produksi Logam (Ton)</th>
                            <th>Status Batch</th>
                            <th class="text-end">Aksi Operasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($furnaceRuns as $run)
                            <tr>
                                <td class="font-weight-bold text-primary">
                                    {{ $run->run_code }}
                                    <div class="text-muted small">{{ $run->batch_lot_number }}</div>
                                </td>
                                <td>{{ $run->furnace?->furnace_code ?? 'FURNACE-RKEF-01' }}</td>
                                <td>
                                    {{ number_format((float) $run->ore_feed_wmt, 1) }} WMT /
                                    <span class="text-dark font-weight-bold">{{ number_format((float) $run->ore_feed_dmt, 1) }} DMT</span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">{{ number_format((float) $run->ore_ni_grade_pct, 2) }}% Ni</span>
                                    <span class="badge bg-secondary">{{ number_format((float) $run->ore_fe_grade_pct, 1) }}% Fe</span>
                                </td>
                                <td>{{ number_format((float) $run->power_consumption_mwh, 2) }} MWh</td>
                                <td class="font-weight-bold text-success">
                                    {{ number_format((float) $run->metal_output_tons, 2) }} Ton
                                </td>
                                <td>
                                    <span class="badge {{ $run->statusEnum()->badgeClass() }}">
                                        {{ $run->statusEnum()->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="{{ route('mining.fase121.smelter.balance') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="run_id" value="{{ $run->id }}">
                                            <button type="submit" class="btn btn-outline-info" title="Hitung Neraca Termal">
                                                <i class="fas fa-calculator"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('mining.fase121.smelter.hilirisasi-pipeline') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="run_id" value="{{ $run->id }}">
                                            <button type="submit" class="btn btn-outline-success" title="Jalankan Pipeline Hilirisasi">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada siklus batch peleburan yang tercatat. Silakan jadwalkan rencana run tanur baru.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Run Tanur -->
<div class="modal fade" id="newFurnaceRunModal" tabindex="-1" aria-labelledby="newFurnaceRunLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('mining.fase121.smelter.runs.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="newFurnaceRunLabel">Jadwalkan Run Tanur Smelter RKEF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pilih Tanur Peleburan</label>
                        <select name="furnace_id" class="form-select" required>
                            @foreach($furnaces as $furnace)
                                <option value="{{ $furnace->id }}">{{ $furnace->name }} ({{ $furnace->furnace_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Umpan Basah (WMT)</label>
                            <input type="number" step="0.1" name="ore_feed_wmt" class="form-control" value="120.0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Kadar Air (% Moisture)</label>
                            <input type="number" step="0.1" name="ore_moisture_pct" class="form-control" value="32.0" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Kadar Ni (%)</label>
                            <input type="number" step="0.01" name="ore_ni_grade_pct" class="form-control" value="1.85" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Kadar Fe (%)</label>
                            <input type="number" step="0.01" name="ore_fe_grade_pct" class="form-control" value="18.50" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Reduktor Kokas (Ton)</label>
                            <input type="number" step="0.1" name="coal_reductant_tons" class="form-control" value="14.5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Flux Silika (Ton)</label>
                            <input type="number" step="0.1" name="silica_flux_tons" class="form-control" value="8.0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan & Jadwalkan Run</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
