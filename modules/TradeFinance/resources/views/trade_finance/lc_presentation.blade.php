@extends('layouts.app')

@section('title', 'L/C Document Presentation & Discrepancy Verification')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">UCP 600 Document Presentation & Compliance Check</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pemeriksaan kepatuhan dokumen ekspor/impor terhadap syarat L/C dan standar UCP 600 untuk mencegah diskrepansi pembayaran bank.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nama Dokumen</th>
                        <th>Nomor Dokumen</th>
                        <th>Diskrepansi</th>
                        <th>Keterangan</th>
                        <th>Status Waiver</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Bill of Lading (Clean on Board)</td>
                        <td>BL-2026-SH-JKT</td>
                        <td><span class="badge bg-success">Compliant</span></td>
                        <td>Port of discharge matches L/C requirements</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>Commercial Invoice</td>
                        <td>INV-EXP-991</td>
                        <td><span class="badge bg-danger">Discrepancy</span></td>
                        <td>Goods description missing partial HS code</td>
                        <td><span class="badge bg-warning text-dark">Waived by Applicant</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
