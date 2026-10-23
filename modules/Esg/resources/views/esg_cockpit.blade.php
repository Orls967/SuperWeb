@extends('layouts.app')

@section('title', 'Corporate Sustainability & ESG Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Corporate Sustainability & ESG Control Cockpit</h3>
            <p class="text-muted mb-0">Scope 1/2/3 Accounting, Carbon Credit Asset Ledgers, and Supplier GRI Compliance</p>
        </div>
        <div>
            <a href="{{ route('esg.operations.index') }}" class="btn btn-primary">Refresh Cockpit</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Recent GHG Emissions Recorded</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref</th>
                                    <th>Scope</th>
                                    <th>Activity</th>
                                    <th>CO2e (kg)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($emissions ?? [] as $em)
                                    <tr>
                                        <td>{{ $em->emission_number }}</td>
                                        <td><span class="badge bg-secondary">{{ $em->scope }}</span></td>
                                        <td>{{ $em->activity_type }}</td>
                                        <td><strong>{{ number_format((float)$em->co2e_kg, 2) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No emissions recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Active Carbon Credit Assets</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Certificate</th>
                                    <th>Registry</th>
                                    <th>Available (Tons)</th>
                                    <th>Total Cost (IDR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($credits ?? [] as $c)
                                    <tr>
                                        <td>{{ $c->certificate_number }}</td>
                                        <td>{{ $c->registry }}</td>
                                        <td>{{ number_format((float)$c->quantity_co2e_tons, 2) }}</td>
                                        <td>Rp {{ number_format($c->total_cost_idr, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No carbon credit inventory recorded.</td>
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
