@extends('layouts.app')

@section('title', 'Mirrored Intercompany Transactions')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Mirrored Intercompany AR/AP Bilateral Postings</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pencatatan transaksi bilateral simultan antara entitas penjual (AR) dan pembeli (AP) dalam grup untuk memastikan tidak ada selisih rekonsiliasi.
            </div>
            <form action="{{ route('intercompany.consolidation.mirror-tx') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="ic-selling-entity" class="form-label">Selling Entity (AR)</label>
                        <input type="text" id="ic-selling-entity" name="selling_entity" class="form-control" placeholder="PT Induk Manufaktur" aria-label="Selling Entity (AR)" required>
                    </div>
                    <div class="col-md-6">
                        <label for="ic-buying-entity" class="form-label">Buying Entity (AP)</label>
                        <input type="text" id="ic-buying-entity" name="buying_entity" class="form-control" placeholder="PT Anak Distribusi" aria-label="Buying Entity (AP)" required>
                    </div>
                    <div class="col-md-8">
                        <label for="ic-description" class="form-label">Description</label>
                        <input type="text" id="ic-description" name="description" class="form-control" aria-label="Description" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ic-amount-idr" class="form-label">Amount (IDR)</label>
                        <input type="number" id="ic-amount-idr" name="amount_idr" class="form-control" aria-label="Amount in IDR" required min="1">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Record Mirrored Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
