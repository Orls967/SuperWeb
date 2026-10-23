@extends('layouts.app')

@section('title', 'HCM Payroll Reconciliation Audit')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Payroll Reconciliation & Tax Authority (SPT Masa) Audit</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Audit matematis periodik: memastikan Gaji Bruto dikurangi seluruh potongan sama persis dengan Transfer Bersih perbankan, dan PPh 21 tercatat identik dengan SPT Masa.
            </div>
            <form action="{{ route('hcm.operations.audit.reconcile') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Periode Penggajian (YYYY-MM)</label>
                        <input type="text" name="period" class="form-control" value="{{ date('Y-m') }}" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-warning text-dark">Jalankan Rekonsiliasi Audit Penggajian</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
