@extends('layouts.app')

@section('title', 'Documentary Letter of Credit (L/C)')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">UCP 600 Documentary Letter of Credit</h5>
            <span class="badge bg-primary">Trade Finance</span>
        </div>
        <div class="card-body">
            <form action="{{ route('trade_finance.operations.lc.issue') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Applicant Name</label>
                        <input type="text" name="applicant_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Beneficiary Name</label>
                        <input type="text" name="beneficiary_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Issuing Bank</label>
                        <input type="text" name="issuing_bank" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Advising Bank</label>
                        <input type="text" name="advising_bank" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Amount (Foreign)</label>
                        <input type="number" name="amount_foreign" class="form-control" required min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Amount Functional (IDR)</label>
                        <input type="number" name="amount_functional_idr" class="form-control" required min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="sight">Sight L/C</option>
                            <option value="usance">Usance L/C</option>
                            <option value="standby">Standby L/C</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Issue Letter of Credit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
