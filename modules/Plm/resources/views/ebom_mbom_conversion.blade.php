@extends('layouts.app')

@section('title', 'EBOM to MBOM Transformation')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Engineering BOM (EBOM) to Manufacturing BOM (MBOM) Release</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Konversi formula R&D menjadi resep produksi pabrik: penyesuaian routing, toleransi scrap, dan standardisasi komponen untuk manufaktur.
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Part Code</th>
                        <th>Kuantitas Engineering (Net)</th>
                        <th>Faktor Scrap / Allowance</th>
                        <th>Kuantitas Produksi (Gross)</th>
                        <th>Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>RESIN-POLY-99</td>
                        <td>100.00</td>
                        <td>2.00%</td>
                        <td>102.00</td>
                        <td>KG</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
