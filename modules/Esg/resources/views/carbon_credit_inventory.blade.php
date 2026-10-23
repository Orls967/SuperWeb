@extends('layouts.app')

@section('title', 'Carbon Credit Inventory & Ledger Asset')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Verra / IDX Carbon Credit Inventory & Double-Entry Ledger</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pencatatan aset sertifikat kredit karbon terverifikasi ke dalam neraca keuangan (inventory:esg:carbon_credit:IDR) dengan nilai nominal minor unit.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nomor Sertifikat</th>
                        <th>Registri</th>
                        <th>Nama Proyek</th>
                        <th>Vintage</th>
                        <th>Saldo (tCO2e)</th>
                        <th>Biaya Total (IDR)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>VCS-2026-KALIMANTAN-01</td>
                        <td>VERRA</td>
                        <td>Katingan Peatland Reforestation</td>
                        <td>2025</td>
                        <td>5.000,00</td>
                        <td>Rp 750.000.000</td>
                        <td><span class="badge bg-success">Active</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
