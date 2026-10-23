@extends('layouts.app')

@section('title', 'Consolidation Elimination Journal')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Group Consolidation Elimination Entries</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Jurnal eliminasi otomatis saldo resiprokal piutang-utang (AR/AP), pendapatan-beban, serta laba antar-perusahaan belum terealisasi dalam persediaan.
            </div>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Kode Eliminasi</th>
                        <th>Periode</th>
                        <th>Tipe Eliminasi</th>
                        <th>Akun Debit</th>
                        <th>Akun Kredit</th>
                        <th>Nominal (IDR)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ELIM-202610-001</td>
                        <td>2026-10</td>
                        <td>RECIPROCAL_AR_AP</td>
                        <td>Utang Usaha Antar-Entitas</td>
                        <td>Piutang Usaha Antar-Entitas</td>
                        <td>Rp 500.000.000</td>
                        <td><span class="badge bg-success">Posted</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
