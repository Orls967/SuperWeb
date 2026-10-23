@extends('layouts.app')

@section('title', 'Transfer Pricing Arm’s Length Markup Engine')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">OECD & PMK Compliant Arm’s Length Transfer Pricing</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Simulasi dan validasi penetapan harga transfer antar entitas afiliasi menggunakan metode arm's-length (CUP, CPM, RPM, TNMM) sesuai koridor benchmark industri.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Kode Rule</th>
                        <th>Kategori Produk</th>
                        <th>Metode TP</th>
                        <th>Rentang Margin Wajar</th>
                        <th>Sumber Benchmark</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>TP-CHEM-CPM</td>
                        <td>Bahan Kimia Industri</td>
                        <td>CPM (Cost Plus)</td>
                        <td>5.00% - 12.00%</td>
                        <td>OECD_STAT_2026</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
