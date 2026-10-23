@extends('layouts.app')

@section('title', 'Group Multi-Entity Consolidated Trial Balance')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Multi-Entity Segment Trial Balance & EBITDA Consolidation</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Konsolidasi laporan keuangan dan neraca saldo lintas anak perusahaan grup konglomerasi beserta eliminasi dan kontribusi EBITDA per segmen.
            </div>
            <form action="{{ route('enterprise_finance.governance.trial_balance.consolidate') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Periode Kuartal (YYYY-QX)</label>
                        <input type="text" name="period_quarter" class="form-control" placeholder="2026-Q3" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Konsolidasikan Neraca Saldo Grup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
