@extends('layouts.app')

@section('title', 'Department Budget Guardrail & Encumbrance')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Budget Guardrail Hard-Stop & Encumbrance Control</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pengawasan komitmen anggaran departemen (encumbrance) dan penegakan batas keras (hard-stop) pengeluaran modal & operasional perusahaan.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Kode Anggaran</th>
                        <th>Pusat Biaya</th>
                        <th>Alokasi (IDR)</th>
                        <th>Komitmen (Encumbered)</th>
                        <th>Realisasi (Spent)</th>
                        <th>Tipe Kontrol</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>BUD-2026-IT-INFRA</td>
                        <td>CC-IT-001</td>
                        <td>Rp 2.000.000.000</td>
                        <td>Rp 200.000.000</td>
                        <td>Rp 950.000.000</td>
                        <td><span class="badge bg-danger">HARD_STOP</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
