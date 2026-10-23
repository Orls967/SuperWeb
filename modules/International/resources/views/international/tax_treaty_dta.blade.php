@extends('layouts.app')

@section('title', 'Double Tax Agreement (DTA / P3B) Relief')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">P3B / Tax Treaty Relief & Withholding Tax Savings</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Negara Mitra</th>
                        <th>Tarif Standar (PPh 26)</th>
                        <th>Tarif Treaty Royalti</th>
                        <th>Tarif Treaty Dividen</th>
                        <th>Tarif Treaty Bunga</th>
                        <th>Wajib Form DGT-1</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Singapore (SG)</td>
                        <td>20%</td>
                        <td>10%</td>
                        <td>10%</td>
                        <td>10%</td>
                        <td><span class="badge bg-success">Wajib</span></td>
                    </tr>
                    <tr>
                        <td>Japan (JP)</td>
                        <td>20%</td>
                        <td>10%</td>
                        <td>10%</td>
                        <td>10%</td>
                        <td><span class="badge bg-success">Wajib</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
