@extends('layouts.app')

@section('title', 'Cryptographic Engineering Change Orders (ECO)')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Cryptographic Engineering Change Order (ECO) Hash Chain</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Integritas rantai hash kriptografis SHA-256 untuk setiap revisi teknik formula / komponen produk guna mencegah manipulasi spesifikasi ilegal.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nomor ECO</th>
                        <th>Judul Revisi</th>
                        <th>Dampak Biaya (IDR)</th>
                        <th>Disposisi</th>
                        <th>Previous Hash</th>
                        <th>Block Hash</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ECO-998811</td>
                        <td>Optimasi Formula Resin Termal</td>
                        <td>Rp 25.000.000</td>
                        <td><span class="badge bg-warning text-dark">Rework</span></td>
                        <td><code>e3b0c44298fc...</code></td>
                        <td><code>7d8f9a0c1b2e...</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
