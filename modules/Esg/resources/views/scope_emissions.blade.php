@extends('layouts.app')

@section('title', 'Scope 1, 2, and 3 GHG Emissions Calculator')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">GHG Protocol Scope 1, Scope 2, and Scope 3 Emissions Tracking</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Kalkulasi emisi gas rumah kaca terstandardisasi GHG Protocol berdasarkan aktivitas bahan bakar, konsumsi listrik jaringan, dan logistik rantai pasok.
            </div>
            <form action="{{ route('esg.operations.emissions.calculate') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Entitas Pelapor</label>
                        <input type="text" name="entity_id" class="form-control" placeholder="PT-ENT-MFG-01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cakupan (Scope)</label>
                        <select name="scope" class="form-select" required>
                            <option value="scope_1">Scope 1 (Direct Combustion)</option>
                            <option value="scope_2">Scope 2 (Purchased Electricity)</option>
                            <option value="scope_3">Scope 3 (Supply Chain & Freight)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipe Aktivitas</label>
                        <input type="text" name="activity_type" class="form-control" placeholder="diesel_generator" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jumlah Aktivitas</label>
                        <input type="number" step="0.0001" name="activity_data_amount" class="form-control" required min="0.0001">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satuan (UoM)</label>
                        <input type="text" name="activity_uom" class="form-control" placeholder="Liters" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Faktor Emisi (kg CO2e/unit)</label>
                        <input type="number" step="0.000001" name="emission_factor" class="form-control" placeholder="2.680000" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Periode Laporan</label>
                        <input type="date" name="reporting_period" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Hitung & Simpan Emisi GHG</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
