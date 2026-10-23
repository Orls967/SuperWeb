@extends('layouts.app')

@section('title', 'Enterprise Finance & Conglomerate Governance Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Group Financial Governance & Conglomerate Control</h3>
            <p class="text-muted mb-0">Multi-Entity Trial Balance, Budget Guardrails, and Debt Covenant Surveillance</p>
        </div>
        <div>
            <a href="{{ route('enterprise_finance.governance.index') }}" class="btn btn-primary">Refresh Dashboard</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Department Budgets & Guardrails</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Budget Code</th>
                                    <th>Cost Center</th>
                                    <th>Allocated (IDR)</th>
                                    <th>Control</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budgets ?? [] as $b)
                                    <tr>
                                        <td>{{ $b->budget_code }}</td>
                                        <td>{{ $b->cost_center_code }}</td>
                                        <td>Rp {{ number_format($b->allocated_amount_idr, 0, ',', '.') }}</td>
                                        <td><span class="badge bg-secondary">{{ $b->control_type }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No budgets configured.</td>
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
                    <h6 class="mb-0">Quarterly Consolidated Segment Reports</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Quarter</th>
                                    <th>Total Revenue</th>
                                    <th>Total EBITDA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports ?? [] as $r)
                                    <tr>
                                        <td>{{ $r->period_quarter }}</td>
                                        <td>Rp {{ number_format($r->total_consolidated_revenue_minor, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($r->total_consolidated_ebitda_minor, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No consolidated reports generated.</td>
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
