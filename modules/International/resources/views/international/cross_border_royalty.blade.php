@extends('layouts.app')

@section('title', 'Cross-Border Technology Royalty & Withholding Tax')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Cross-Border Royalty & PPh 26 Calculation</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Kalkulasi royalti lisensi teknologi internasional dengan perhitungan potongan PPh 26 (Withholding Tax) tarif domestik vs tarif P3B (Tax Treaty).
            </div>
            <form method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Basis Penjualan Bersih (IDR)</label>
                        <input type="number" name="sales_revenue_idr" class="form-control" required min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Form DGT-1 / COD Terverifikasi</label>
                        <select name="has_valid_dgt_form" class="form-select">
                            <option value="1">Ya (Gunakan Tarif Treaty)</option>
                            <option value="0">Tidak (Tarif PPh 26 Normal 20%)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Hitung Royalti & Remitansi Bersih</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
