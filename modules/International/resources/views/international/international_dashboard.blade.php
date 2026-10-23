@extends('layouts.app')

@section('title', 'International Partnerships & Governance Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Global Partnerships & Cross-Border Governance</h3>
            <p class="text-muted mb-0">Cross-Border Royalty, Tax Treaty (P3B) Compliance, and OEM Tolling Operations</p>
        </div>
        <div>
            <a href="{{ route('international.tax_royalty.index') }}" class="btn btn-primary">Refresh Overview</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Foreign Entities & Licensing Partners</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Entity Code</th>
                                    <th>Legal Name</th>
                                    <th>Jurisdiction</th>
                                    <th>Tax Residence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entities ?? [] as $entity)
                                    <tr>
                                        <td>{{ $entity->entity_code }}</td>
                                        <td>{{ $entity->legal_name }}</td>
                                        <td>{{ $entity->jurisdiction_country }}</td>
                                        <td><span class="badge bg-info text-dark">{{ $entity->tax_residence_country }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No foreign entities recorded.</td>
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
                    <h6 class="mb-0">Active Technology Licenses</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>License</th>
                                    <th>Rate</th>
                                    <th>Licensor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($licenses ?? [] as $license)
                                    <tr>
                                        <td>{{ $license->title }}</td>
                                        <td>{{ $license->royalty_rate_percent }}%</td>
                                        <td>{{ $license->foreignEntity?->legal_name ?? 'N/A' }}</td>
                                        <td><span class="badge bg-success">{{ $license->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No licenses found.</td>
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
