@extends('layouts.app')

@section('title', 'Supply Chain Control Tower Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Autonomous Supply Chain Control Tower</h3>
            <p class="text-muted mb-0">Multi-Echelon Visibility, S&OP Forecasting, and Autonomous Disruption Mitigation</p>
        </div>
        <div>
            <a href="{{ route('control_tower.autonomous.index') }}" class="btn btn-primary">Refresh Cockpit</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0">Active Disruption Alerts</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Alert</th>
                                    <th>Severity</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($alerts ?? [] as $alert)
                                    <tr>
                                        <td>{{ $alert->title }}</td>
                                        <td><span class="badge bg-danger">{{ $alert->severity }}</span></td>
                                        <td>{{ $alert->category }}</td>
                                        <td><span class="badge bg-warning text-dark">{{ $alert->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">Zero active supply chain disruptions.</td>
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
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Recent S&OP Demand Forecasts</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Period</th>
                                    <th>Model</th>
                                    <th>Forecast Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($forecasts ?? [] as $fc)
                                    <tr>
                                        <td>{{ $fc->item_code }}</td>
                                        <td>{{ $fc->period }}</td>
                                        <td>{{ $fc->algorithm_model }}</td>
                                        <td><strong>{{ number_format($fc->forecast_qty) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No forecasts generated yet.</td>
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
