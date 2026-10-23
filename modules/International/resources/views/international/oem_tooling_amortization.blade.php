@extends('layouts.app')

@section('title', 'OEM/ODM Tooling Amortization & Tolling Fee')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">OEM/ODM Tooling Cost Amortization Reconciliation</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Rekonsiliasi biaya amortisasi cetakan (molding/tooling) per unit output produksi OEM lintas batas beserta biaya jasa tolling manufaktur.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nomor Kontrak</th>
                        <th>Jasa Tolling / Unit</th>
                        <th>Alokasi Amortisasi Tooling</th>
                        <th>Total Produksi Berjalan</th>
                        <th>Sisa Nilai Buku Tooling</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>OEM-2026-PRECISION-01</td>
                        <td>Rp 25.000</td>
                        <td>Rp 5.000 / unit</td>
                        <td>100.000 unit</td>
                        <td>Rp 0 (Fully Amortized)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
