@extends('layouts.app')

@section('title', 'Trade Finance Operations Cockpit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Global Trade Finance Operations</h3>
            <p class="text-muted mb-0">UCP 600 Letter of Credit, Documentary Presentation, and SCF Factoring</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('trade_finance.operations.index') }}" class="btn btn-outline-primary">Refresh Cockpit</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Recent Letters of Credit</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>LC Number</th>
                                    <th>Beneficiary</th>
                                    <th>Amount (IDR)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lcs ?? [] as $lc)
                                    <tr>
                                        <td>{{ $lc->lc_number }}</td>
                                        <td>{{ $lc->beneficiary_name }}</td>
                                        <td>Rp {{ number_format($lc->amount_functional_idr, 0, ',', '.') }}</td>
                                        <td><span class="badge bg-secondary">{{ $lc->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No letters of credit issued yet.</td>
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
                    <h6 class="mb-0">Active Supply Chain Finance Loans</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Loan Number</th>
                                    <th>Borrower</th>
                                    <th>Principal (IDR)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($loans ?? [] as $loan)
                                    <tr>
                                        <td>{{ $loan->loan_number }}</td>
                                        <td>{{ $loan->borrower_name }}</td>
                                        <td>Rp {{ number_format($loan->principal_amount_idr, 0, ',', '.') }}</td>
                                        <td><span class="badge bg-success">{{ $loan->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No active SCF loans.</td>
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
