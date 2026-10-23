@extends('layouts.app')

@section('title', 'Supply Chain Bottleneck & Disruption Alerts')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Multi-Echelon Inventory Bottleneck Radar</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                Deteksi dini stockout dan gangguan rantai pasok: membandingkan saldo efektif (on hand + in transit - reserved) dengan safety stock.
            </div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kode Peringatan</th>
                        <th>Tingkat Keparahan</th>
                        <th>Judul Peringatan</th>
                        <th>Estimasi Order Terdampak</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ALT-SKU-99-PLANT</td>
                        <td><span class="badge bg-danger">CRITICAL</span></td>
                        <td>Stockout Risk on SKU-99 at PLANT</td>
                        <td>12 Pesanan</td>
                        <td><span class="badge bg-warning text-dark">Active</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
