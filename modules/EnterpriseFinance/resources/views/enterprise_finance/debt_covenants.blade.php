@extends('layouts.app')

@section('title', 'Enterprise Debt Covenant Ratio Monitoring')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Syndicated Loan Debt Covenant Compliance Radar</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Monitoring rasio keuangan terikat perbankan: Debt-to-Equity Ratio (DER) dan Interest Coverage Ratio (ICR = EBITDA / Bunga) untuk mendeteksi ancaman default pinjaman.
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Indikator Rasio</th>
                        <th>Batas Maksimal / Minimal</th>
                        <th>Rasio Aktual</th>
                        <th>Status Kepatuhan Kovenan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Debt to Equity Ratio (DER)</td>
                        <td>Maks 2.50x</td>
                        <td>1.45x</td>
                        <td><span class="badge bg-success">COMPLIANT</span></td>
                    </tr>
                    <tr>
                        <td>Interest Coverage Ratio (ICR)</td>
                        <td>Min 3.00x</td>
                        <td>4.80x</td>
                        <td><span class="badge bg-success">COMPLIANT</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
