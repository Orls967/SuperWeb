@extends('layouts.app')

@section('title', 'Net-Zero Carbon Offset Retirements')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Permanent Carbon Credit Retirements & Neutralization Claims</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pensiun permanen unit kredit karbon guna klaim Net-Zero emisi operasional: membukukan pengeluaran biaya netralisasi karbon dan mengurangi persediaan kredit.
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nomor Pensiun</th>
                        <th>Sertifikat Kredit</th>
                        <th>Entitas Offset</th>
                        <th>Kuantitas Pensiun (Tons)</th>
                        <th>Alasan Netralisasi</th>
                        <th>Tanggal Pensiun</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>RET-2026-NZ-001</td>
                        <td>VCS-2026-KALIMANTAN-01</td>
                        <td>PT AutoServe EV Fleet</td>
                        <td>1.250,00</td>
                        <td>Neutralize Q3 Scope 1 Fuel Emissions</td>
                        <td>{{ date('d M Y') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
