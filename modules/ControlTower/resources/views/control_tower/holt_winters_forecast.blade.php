@extends('layouts.app')

@section('title', 'S&OP Holt-Winters Consensus Demand Forecast')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Holt-Winters Statistical Exponential Smoothing (S&OP)</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Kalkulasi peramalan permintaan tingkat tinggi menggunakan model Holt-Winters untuk menangkap tren dan musiman rantai pasok multi-eselon.
            </div>
            <form action="{{ route('control_tower.autonomous.forecast') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="hw_item_code" class="form-label">Kode Item</label>
                        <input id="hw_item_code" type="text" name="item_code" class="form-control" placeholder="SKU-CHIP-99" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hw_period" class="form-label">Periode (YYYY-MM)</label>
                        <input id="hw_period" type="text" name="period" class="form-control" value="{{ date('Y-m') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hw_alpha" class="form-label">Alpha (Level Weight)</label>
                        <input id="hw_alpha" type="number" step="0.01" name="alpha" class="form-control" value="0.30" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Jalankan Algoritma Forecasting</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
