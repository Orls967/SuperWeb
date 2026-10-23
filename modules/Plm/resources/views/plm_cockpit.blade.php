@extends('layouts.app')

@section('title', 'Product Lifecycle Management Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">PLM & R&D Innovation Cockpit</h3>
            <p class="text-muted mb-0">Stage-Gate Governance, EBOM-to-MBOM Pipelines, and Cryptographic Change Orders</p>
        </div>
        <div>
            <a href="{{ route('plm.operations.index') }}" class="btn btn-primary">Refresh Cockpit</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Active R&D Projects</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Project Name</th>
                                    <th>Current Stage</th>
                                    <th>Budget (IDR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($projects ?? [] as $proj)
                                    <tr>
                                        <td>{{ $proj->code }}</td>
                                        <td>{{ $proj->name }}</td>
                                        <td><span class="badge bg-info text-dark">{{ $proj->stage }}</span></td>
                                        <td>Rp {{ number_format($proj->budget_rd_idr, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No active R&D projects.</td>
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
                    <h6 class="mb-0">Engineering BOM Releases</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>BOM Number</th>
                                    <th>Project</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eboms ?? [] as $b)
                                    <tr>
                                        <td>{{ $b->bom_number }}</td>
                                        <td>{{ $b->project?->name ?? 'N/A' }}</td>
                                        <td>{{ $b->version }}</td>
                                        <td><span class="badge bg-secondary">{{ $b->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No EBOMs registered.</td>
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
