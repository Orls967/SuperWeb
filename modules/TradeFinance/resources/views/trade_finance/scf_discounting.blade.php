@extends('layouts.app')

@section('title', 'Supply Chain Finance (SCF) Invoice Discounting')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Supply Chain Finance & Factoring Calculator</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('trade_finance.operations.scf.discount') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Borrower / Vendor Name</label>
                        <input type="text" name="borrower_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Principal Invoice Amount (IDR)</label>
                        <input type="number" name="principal_amount_idr" class="form-control" required min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Annual Discount Rate (%)</label>
                        <input type="number" step="0.01" name="annual_rate_percent" class="form-control" value="7.50" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tenor (Days)</label>
                        <input type="number" name="tenor_days" class="form-control" value="90" required min="1">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Disburse Early SCF Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
