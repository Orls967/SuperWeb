@extends('layouts.app')

@section('title', 'Enterprise HCM & Payroll Governance Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Enterprise Human Capital & Payroll Cockpit</h3>
            <p class="text-muted mb-0">Org Structures, Biometric Attendance, PPh 21 TER, BPJS, and Manufacturing Labor Allocation</p>
        </div>
        <div>
            <a href="{{ route('hcm.operations.index') }}" class="btn btn-primary">Refresh Cockpit</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Active Departments</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Department Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($departments ?? [] as $dept)
                                    <tr>
                                        <td>{{ $dept->code }}</td>
                                        <td>{{ $dept->name }}</td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No departments configured.</td>
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
                    <h6 class="mb-0">Recent Payroll Runs</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Gross (IDR)</th>
                                    <th>Net (IDR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payrolls ?? [] as $p)
                                    <tr>
                                        <td>{{ $p->employee?->name ?? 'N/A' }}</td>
                                        <td>{{ $p->period }}</td>
                                        <td>Rp {{ number_format($p->gross_salary_idr, 0, ',', '.') }}</td>
                                        <td><strong>Rp {{ number_format($p->net_salary_idr, 0, ',', '.') }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No payroll entries recorded.</td>
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
