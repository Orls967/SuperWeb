@extends('layouts.app')

@section('title', 'Direct Labor Cost Allocation to Manufacturing')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Shop Floor Direct Labor Absorption & Allocation</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Alokasi biaya tenaga kerja langsung (Direct Labor) dari daftar gaji (Payroll) ke surat perintah kerja manufaktur (Work Order WIP) guna akurasi perhitungan HPP / COGM.
            </div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Work Order Ref</th>
                        <th>Jam Kerja Terekam</th>
                        <th>Biaya Tenaga Kerja Teralokasi (IDR)</th>
                        <th>Akun Debit Persediaan</th>
                        <th>Akun Kredit Absorpsi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>WO-2026-CHASSIS-001</td>
                        <td>40.00 Jam</td>
                        <td>Rp 2.000.000</td>
                        <td>inventory:mfg:wip_labor:IDR</td>
                        <td>expense:hcm:labor_absorbed:IDR</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
